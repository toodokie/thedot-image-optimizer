# Telemetry Integration Guide

## Overview

The MSH Image Optimizer now includes telemetry hooks for logging AI quality metrics and token usage to Supabase. This guide explains how to integrate with your telemetry system.

## Available Telemetry Hooks

### 1. AI Batch Completion Telemetry

**Hook Location:** `includes/class-msh-image-optimizer.php:8098`

**Trigger:** After each AI regeneration batch completes

**Current Implementation (Logged):**
```php
error_log( sprintf(
    '[MSH Telemetry] AI Batch Complete - processed: %d, success: %d, fallback: %d, confidence_avg: %.2f, brand_assumed: %d, decorative: %d, text_detected: %d, low_confidence: %d',
    $total_to_process,
    $telemetry['ai_success_count'],
    $telemetry['ai_fallback_count'],
    $confidence_avg,
    $telemetry['brand_name_assumed_count'],
    $telemetry['decorative_image_count'],
    $telemetry['text_detected_count'],
    $telemetry['low_confidence_count']
) );
```

**Future Hook (Ready to Uncomment):**
```php
do_action( 'msh_log_telemetry', 'ai_batch_complete', $telemetry );
```

**Data Structure:**
```php
$telemetry = array(
    'confidence_scores'        => array( 0.85, 0.92, 0.78, ... ), // All confidence values
    'brand_name_assumed_count' => 0,  // How many violated brand_name_visible rules
    'decorative_image_count'   => 1,  // Images classified as decorative
    'text_detected_count'      => 2,  // Images with text/signage detected
    'low_confidence_count'     => 0,  // Confidence < 0.70
    'ai_success_count'         => 9,  // Successful AI generations
    'ai_fallback_count'        => 1,  // Fell back to heuristic metadata
);
```

**Calculated Metrics:**
```php
$confidence_avg = array_sum( $telemetry['confidence_scores'] ) / count( $telemetry['confidence_scores'] );
```

### 2. Token Usage Per Image

**Hook Location:** `includes/class-msh-openai-connector.php:160`

**Trigger:** After each successful OpenAI API call

**Current Implementation (Logged):**
```php
error_log( sprintf(
    '[MSH OpenAI] Token usage - prompt: %d, completion: %d, total: %d',
    $tokens_used['prompt_tokens'],
    $tokens_used['completion_tokens'],
    $tokens_used['total_tokens']
) );
```

**Future Hook (Ready to Uncomment):**
```php
do_action( 'msh_log_token_usage', $attachment_id, $tokens_used, self::PROMPT_VERSION );
```

**Data Structure:**
```php
$tokens_used = array(
    'prompt_tokens'     => 1247,  // Tokens in prompt (SYSTEM + USER + image)
    'completion_tokens' => 89,    // Tokens in AI response
    'total_tokens'      => 1336,  // Total billable tokens
);
```

**Additional Context:**
- `$attachment_id`: WordPress attachment ID
- `self::PROMPT_VERSION`: e.g., "20251029.1"

## Supabase Integration

### Table Schema

**`ai_batch_telemetry` Table:**
```sql
CREATE TABLE ai_batch_telemetry (
    id BIGSERIAL PRIMARY KEY,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    site_id VARCHAR(255),
    batch_size INT NOT NULL,
    ai_success_count INT DEFAULT 0,
    ai_fallback_count INT DEFAULT 0,
    confidence_avg DECIMAL(3,2),
    brand_name_assumed_count INT DEFAULT 0,
    decorative_image_count INT DEFAULT 0,
    text_detected_count INT DEFAULT 0,
    low_confidence_count INT DEFAULT 0,
    prompt_version VARCHAR(50),
    processing_duration_ms INT
);

CREATE INDEX idx_ai_batch_created ON ai_batch_telemetry(created_at);
CREATE INDEX idx_ai_batch_site ON ai_batch_telemetry(site_id);
CREATE INDEX idx_ai_batch_prompt_version ON ai_batch_telemetry(prompt_version);
```

**`token_usage` Table:**
```sql
CREATE TABLE token_usage (
    id BIGSERIAL PRIMARY KEY,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    site_id VARCHAR(255),
    attachment_id BIGINT,
    prompt_tokens INT NOT NULL,
    completion_tokens INT NOT NULL,
    total_tokens INT NOT NULL,
    prompt_version VARCHAR(50),
    context_type VARCHAR(50),
    brand_name_visible BOOLEAN
);

CREATE INDEX idx_token_usage_created ON token_usage(created_at);
CREATE INDEX idx_token_usage_site ON token_usage(site_id);
CREATE INDEX idx_token_usage_prompt_version ON token_usage(prompt_version);
```

### WordPress Integration

**Create Supabase Connector Class:**

```php
<?php
/**
 * Supabase Telemetry Connector
 */
class MSH_Supabase_Telemetry {

    private $supabase_url;
    private $supabase_key;

    public function __construct() {
        $this->supabase_url = get_option( 'msh_supabase_url' );
        $this->supabase_key = get_option( 'msh_supabase_anon_key' );

        // Hook into telemetry actions
        add_action( 'msh_log_telemetry', array( $this, 'log_batch_telemetry' ), 10, 2 );
        add_action( 'msh_log_token_usage', array( $this, 'log_token_usage' ), 10, 3 );
    }

    public function log_batch_telemetry( $event_type, $data ) {
        if ( $event_type !== 'ai_batch_complete' ) {
            return;
        }

        $confidence_avg = ! empty( $data['confidence_scores'] )
            ? array_sum( $data['confidence_scores'] ) / count( $data['confidence_scores'] )
            : 0.0;

        $payload = array(
            'site_id'                   => $this->get_site_id(),
            'batch_size'                => $data['ai_success_count'] + $data['ai_fallback_count'],
            'ai_success_count'          => $data['ai_success_count'],
            'ai_fallback_count'         => $data['ai_fallback_count'],
            'confidence_avg'            => round( $confidence_avg, 2 ),
            'brand_name_assumed_count'  => $data['brand_name_assumed_count'],
            'decorative_image_count'    => $data['decorative_image_count'],
            'text_detected_count'       => $data['text_detected_count'],
            'low_confidence_count'      => $data['low_confidence_count'],
            'prompt_version'            => MSH_OpenAI_Connector::PROMPT_VERSION,
        );

        $this->insert_record( 'ai_batch_telemetry', $payload );
    }

    public function log_token_usage( $attachment_id, $tokens_used, $prompt_version ) {
        $payload = array(
            'site_id'           => $this->get_site_id(),
            'attachment_id'     => $attachment_id,
            'prompt_tokens'     => $tokens_used['prompt_tokens'],
            'completion_tokens' => $tokens_used['completion_tokens'],
            'total_tokens'      => $tokens_used['total_tokens'],
            'prompt_version'    => $prompt_version,
        );

        $this->insert_record( 'token_usage', $payload );
    }

    private function insert_record( $table, $data ) {
        $response = wp_remote_post(
            "{$this->supabase_url}/rest/v1/{$table}",
            array(
                'headers' => array(
                    'apikey'        => $this->supabase_key,
                    'Authorization' => 'Bearer ' . $this->supabase_key,
                    'Content-Type'  => 'application/json',
                    'Prefer'        => 'return=minimal',
                ),
                'body'    => wp_json_encode( $data ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            error_log( '[MSH Supabase] Insert failed: ' . $response->get_error_message() );
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 201 ) {
            error_log( "[MSH Supabase] Insert failed with status {$status_code}" );
            return false;
        }

        return true;
    }

    private function get_site_id() {
        // Use site URL as identifier
        return parse_url( get_site_url(), PHP_URL_HOST );
    }
}

// Initialize if Supabase configured
if ( get_option( 'msh_supabase_url' ) && get_option( 'msh_supabase_anon_key' ) ) {
    new MSH_Supabase_Telemetry();
}
```

### Activation Steps

1. **Add Supabase Settings:**
   ```php
   update_option( 'msh_supabase_url', 'https://your-project.supabase.co' );
   update_option( 'msh_supabase_anon_key', 'your-anon-key' );
   ```

2. **Uncomment Telemetry Hooks:**
   - `class-msh-image-optimizer.php:8098`
   - `class-msh-openai-connector.php:160`

3. **Load Supabase Connector:**
   ```php
   require_once plugin_dir_path( __FILE__ ) . 'includes/class-msh-supabase-telemetry.php';
   ```

## Query Examples

### Average Confidence by Prompt Version

```sql
SELECT
    prompt_version,
    COUNT(*) as batches,
    AVG(confidence_avg) as avg_confidence,
    AVG(ai_success_count::DECIMAL / NULLIF(batch_size, 0)) as success_rate
FROM ai_batch_telemetry
WHERE created_at > NOW() - INTERVAL '30 days'
GROUP BY prompt_version
ORDER BY created_at DESC;
```

### Token Cost Trend

```sql
SELECT
    DATE(created_at) as date,
    prompt_version,
    SUM(total_tokens) as total_tokens,
    AVG(total_tokens) as avg_tokens_per_image,
    COUNT(*) as images_processed
FROM token_usage
WHERE created_at > NOW() - INTERVAL '7 days'
GROUP BY DATE(created_at), prompt_version
ORDER BY date DESC;
```

### Quality Issues Breakdown

```sql
SELECT
    created_at::DATE as date,
    SUM(brand_name_assumed_count) as brand_assumed_total,
    SUM(decorative_image_count) as decorative_total,
    SUM(text_detected_count) as text_detected_total,
    SUM(low_confidence_count) as low_confidence_total
FROM ai_batch_telemetry
WHERE created_at > NOW() - INTERVAL '30 days'
GROUP BY created_at::DATE
ORDER BY date DESC;
```

### A/B Testing Prompt Versions

```sql
WITH version_stats AS (
    SELECT
        prompt_version,
        AVG(confidence_avg) as avg_confidence,
        AVG(ai_success_count::DECIMAL / NULLIF(batch_size, 0)) * 100 as success_rate_pct,
        AVG(brand_name_assumed_count::DECIMAL / NULLIF(batch_size, 0)) * 100 as violation_rate_pct,
        COUNT(*) as batches
    FROM ai_batch_telemetry
    WHERE created_at > NOW() - INTERVAL '7 days'
    GROUP BY prompt_version
)
SELECT
    prompt_version,
    ROUND(avg_confidence, 2) as avg_confidence,
    ROUND(success_rate_pct, 1) || '%' as success_rate,
    ROUND(violation_rate_pct, 2) || '%' as violation_rate,
    batches
FROM version_stats
ORDER BY avg_confidence DESC;
```

## Dashboard Metrics

**Key Performance Indicators:**

1. **Quality Score:**
   ```
   Quality Score = (confidence_avg * 0.5) + (success_rate * 0.3) + ((1 - violation_rate) * 0.2)
   ```

2. **Token Efficiency:**
   ```
   Avg Tokens per Image = total_tokens / images_processed
   ```

3. **Cost Tracking:**
   ```
   Daily Cost = (total_tokens / 1000) * $0.01  // GPT-4o Vision pricing
   ```

4. **Decorative Detection Rate:**
   ```
   Decorative Rate = decorative_image_count / batch_size
   ```

## Privacy Considerations

**Data Collected:**
- ✅ Aggregate batch metrics (no PII)
- ✅ Token usage (no image content)
- ✅ Prompt versions
- ❌ NO image content stored
- ❌ NO client-specific metadata
- ❌ NO user information

**GDPR Compliance:**
- All metrics anonymized by default
- Site ID is domain name only
- No personal data collected
- Can be disabled per installation

## Troubleshooting

**Telemetry Not Logging:**
1. Check Supabase credentials
2. Verify table schemas match
3. Check `error_log` for Supabase errors
4. Ensure hooks are uncommented

**High Token Usage:**
1. Check `detail:'high'` is necessary
2. Review prompt length
3. Consider batch size limits

**Low Confidence Scores:**
1. Review prompt version
2. Check image quality
3. Verify context_type accuracy

---

**Related:** See [PHASE6-AI-IMPROVEMENTS.md](../archive/2025-10-october/planning/PHASE6-AI-IMPROVEMENTS.md) for implementation details.
