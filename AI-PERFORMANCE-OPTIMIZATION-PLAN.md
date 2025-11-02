# AI Performance Optimization Plan - November 2, 2024

## Current State Analysis

### Performance Baseline
- **Current**: 6-7 minutes for 35-48 images (8.4 sec/image)
- **Target**: 1.5-2 minutes for 35-48 images (~2-3 sec/image)
- **Improvement needed**: 3-4x speedup

### Root Cause: Sequential Base64 Processing

**File**: `class-msh-openai-connector.php` lines 651-676

```php
if ( $is_local ) {
    $image_data = file_get_contents( $absolute_path );
    $base64 = base64_encode( $image_data );  // ← SLOW: Encodes full-size images
    return "data:{$mime_type};base64,{$base64}";
}
```

**Problems**:
1. ❌ **Sequential processing**: One image at a time
2. ❌ **Full-size base64**: Encodes images at full resolution (multi-MB files)
3. ❌ **No parallelization**: PHP blocks on each API call
4. ❌ **No timing instrumentation**: Can't measure bottlenecks
5. ❌ **Context rebuilt per image**: Business context regenerated 48 times

## Optimization Strategy

### Phase 1: Image Payload Optimization (Quick Win)
**Impact**: 50-60% speedup
**Effort**: 2-3 hours

1. **Resize before encoding** (lines 663-669)
   - Resize to max 1600px long edge
   - Reduce JPEG quality to 80%
   - Target: < 200KB per image
   - Saves: ~5-6 seconds per image in encoding + token costs

```php
// Before base64 encoding:
$resized_path = $this->resize_for_ai( $absolute_path );
$image_data = file_get_contents( $resized_path );
$base64 = base64_encode( $image_data );
```

2. **Set temperature to 0** (line 589)
   - Deterministic outputs
   - No variance in retries
   ```php
   'temperature' => 0,  // Changed from 0.7
   ```

### Phase 2: Parallel Processing (Major Win)
**Impact**: 3-5x speedup
**Effort**: 4-6 hours

**Current flow** (Sequential):
```
Image 1: prep(0.5s) + AI(8s) + db(0.2s) = 8.7s
Image 2: prep(0.5s) + AI(8s) + db(0.2s) = 8.7s
...
Total for 35: 35 × 8.7s = 304s (5 min)
```

**Optimized flow** (Parallel, concurrency=5):
```
Batch 1-5:   prep(0.5s) + AI(8s in parallel) + db(0.2s) = 8.7s
Batch 6-10:  prep(0.5s) + AI(8s in parallel) + db(0.2s) = 8.7s
...
Total for 35: 7 batches × 8.7s = 61s (~1 min)
```

**Implementation**:
1. **Add concurrent queue class**
   - File: `includes/class-msh-concurrent-queue.php`
   - Uses `curl_multi` for parallel HTTP requests
   - Configurable concurrency (default: 5)

2. **Modify analyze_images_batch()** (class-msh-image-optimizer.php)
   - Collect images into batches of 5
   - Submit batch to concurrent queue
   - Process responses as they complete
   - Continue with next batch

```php
// Pseudocode:
$queue = new MSH_Concurrent_Queue( 5 ); // concurrency=5
foreach ( $images as $image ) {
    $queue->add( $image['ID'], $ai_options );
}
$results = $queue->execute(); // Parallel execution
foreach ( $results as $image_id => $metadata ) {
    $this->save_ai_metadata( $image_id, $metadata );
}
```

### Phase 3: Micro-Timing Instrumentation (Diagnostic)
**Impact**: Visibility into bottlenecks
**Effort**: 1-2 hours

**Add timing at each stage**:

```php
// In analyze_single_image():
$t0 = microtime(true);

// Stage 1: Prep
$prep_start = microtime(true);
$image_url = wp_get_attachment_url( $attachment_id );
$context = $this->detect_context( $attachment_id );
$prep_time = microtime(true) - $prep_start;

// Stage 2: AI call
$ai_start = microtime(true);
$metadata = MSH_AI_Service::get_instance()->maybe_generate_metadata( $attachment_id, $context, $this, $ai_options );
$ai_time = microtime(true) - $ai_start;

// Stage 3: DB writes
$db_start = microtime(true);
update_post_meta( $attachment_id, '_msh_ai_staged_meta', $metadata );
$db_time = microtime(true) - $db_start;

$total_time = microtime(true) - $t0;

error_log( sprintf(
    '[MSH TIMING] Image #%d: prep=%.2fs ai=%.2fs db=%.2fs total=%.2fs',
    $attachment_id, $prep_time, $ai_time, $db_time, $total_time
) );
```

### Phase 4: Context Caching (Small Win)
**Impact**: ~0.1-0.2s per image
**Effort**: 30 minutes

**Current**: Business context is merged per image (line 363 in class-msh-ai-service.php)

**Optimization**: Cache merged context once per batch

```php
// At batch start in analyze_images_batch():
$active_context = MSH_Image_Optimizer_Context_Helper::get_active_context();
$this->cached_business_context = $active_context; // Store in class property

// In maybe_generate_metadata():
$merged_context = ! empty( $this->cached_business_context )
    ? array_merge( $this->cached_business_context, $context )
    : $context;
```

### Phase 5: Chunked DB Writes (Small Win)
**Impact**: ~0.1s per image
**Effort**: 1-2 hours

**Current**: Each postmeta write is a separate query

**Optimization**: Collect metadata and write in chunks

```php
// Collect updates:
$meta_updates = array();
foreach ( $results as $image_id => $metadata ) {
    $meta_updates[ $image_id ] = $metadata;
}

// Batch write every 10 images:
if ( count( $meta_updates ) >= 10 ) {
    $this->batch_update_postmeta( $meta_updates );
    $meta_updates = array();
}
```

### Phase 6: Safe Timeouts & Retries (Reliability)
**Impact**: Prevents batch stalls
**Effort**: 1 hour

**Current**: 30s timeout, no retry logic

**Optimization**:
```php
$max_retries = 1;
$retry_count = 0;
$response = null;

while ( $retry_count <= $max_retries ) {
    $response = wp_remote_post( self::API_ENDPOINT, array(
        'timeout' => 15,  // Reduced from 30s
        'headers' => array( ... ),
        'body'    => wp_json_encode( $body ),
    ) );

    if ( ! is_wp_error( $response ) ) {
        break; // Success
    }

    $error_message = $response->get_error_message();
    if ( strpos( $error_message, 'timeout' ) !== false || strpos( $error_message, 'connection' ) !== false ) {
        $retry_count++;
        error_log( "[MSH OpenAI] Retry {$retry_count}/{$max_retries} for attachment {$attachment_id}" );
        usleep( 500000 ); // 500ms delay before retry
    } else {
        break; // Non-transient error, don't retry
    }
}
```

## Implementation Priority

### Must Have (v1.2.16)
1. ✅ **Resize images before base64** - Immediate 50% speedup
2. ✅ **Parallel processing (concurrency=3)** - 3x speedup
3. ✅ **Micro-timing logs** - Diagnostic visibility

**Target**: 2 minutes for 35 images

### Nice to Have (v1.2.17)
4. ⚠️ **Context caching** - Small incremental gain
5. ⚠️ **Chunked DB writes** - Marginal improvement
6. ⚠️ **Retry logic** - Reliability improvement

### Future Enhancement (v1.3.0)
7. 🔮 **Origin-pull for local dev** - Use ngrok or LocalTunnel
8. 🔮 **Adaptive concurrency** - Monitor CPU and adjust on the fly
9. 🔮 **WebP conversion** - Use WebP for AI API calls

## Expected Results

| Optimization | Before | After | Speedup |
|--------------|--------|-------|---------|
| **Baseline** | 6-7 min (35 images) | - | - |
| + Resize images | 6-7 min | 3-4 min | 2x |
| + Parallel (concurrency=3) | 3-4 min | 1.5-2 min | 2x |
| + Context cache | 1.5-2 min | 1.3-1.8 min | 1.1x |
| **Final** | **6-7 min** | **1.5-2 min** | **3.5-4x** |

## Testing Checklist

- [ ] Test with 35 images on Local by Flywheel
- [ ] Verify timing logs show prep/ai/db breakdown
- [ ] Confirm concurrency=3 doesn't stall on local PHP
- [ ] Check that resized images maintain quality
- [ ] Verify no timeouts or batch stalls
- [ ] Confirm partial results render per row
- [ ] Test with concurrency=5 on staging (if available)

## Files to Modify

1. **includes/class-msh-openai-connector.php**
   - Add `resize_for_ai()` method
   - Change temperature to 0
   - Add retry logic

2. **includes/class-msh-concurrent-queue.php** (NEW)
   - Parallel HTTP request handler
   - curl_multi implementation

3. **includes/class-msh-image-optimizer.php**
   - Modify `analyze_images_batch()` for parallel processing
   - Add micro-timing instrumentation
   - Cache business context per batch

4. **includes/class-msh-ai-service.php**
   - Use cached business context

## Risk Assessment

**Low Risk**:
- Resizing images (non-destructive, only for AI)
- Micro-timing logs (diagnostic only)
- Temperature change (deterministic outputs)

**Medium Risk**:
- Parallel processing (test with concurrency=3 first)
- Context caching (ensure no stale data)

**High Risk**:
- None (all changes are performance optimizations, no behavior changes)

## Rollback Plan

If performance degrades:
1. Revert to v1.2.15 (current version)
2. Check error logs for timing breakdown
3. Disable parallel processing (set concurrency=1)
4. Test with smaller batch sizes

---

**Status**: Ready for implementation
**Estimated Effort**: 8-12 hours total
**Expected Delivery**: v1.2.16 (Phase 1+2+3)
**Target Performance**: 1.5-2 min for 35 images (from 6-7 min)
