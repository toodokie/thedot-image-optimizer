# MSH Image Optimizer - REST API Documentation

**Version:** 2.0.0
**Namespace:** `msh/v1`
**Base URL:** `https://yoursite.com/wp-json/msh/v1/`

---

## Authentication

All endpoints require administrator privileges (`manage_options` capability). Authentication is handled via WordPress's standard authentication mechanisms:

- **Cookie Authentication** (for logged-in admin users)
- **Application Passwords** (recommended for external integrations)
- **OAuth** (if OAuth plugin installed)

---

## Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/jobs/status` | Get queue status and statistics |
| POST | `/jobs/process` | Process jobs from the queue |
| GET | `/jobs/{id}` | Get a specific job by ID |
| GET | `/metadata/cache` | Get metadata cache entries |
| POST | `/metadata/regenerate` | Queue metadata regeneration job |
| POST | `/telemetry` | Log telemetry event |

---

## Job Queue Endpoints

### GET /jobs/status

Get current job queue status and statistics.

**Request:**
```bash
curl -X GET https://yoursite.com/wp-json/msh/v1/jobs/status \
  -H "Authorization: Bearer YOUR_APP_PASSWORD"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "stats": {
      "total": 45,
      "pending": 12,
      "processing": 2,
      "complete": 28,
      "failed": 3,
      "priorities": {
        "high": 5,
        "medium": 4,
        "normal": 3
      }
    },
    "timestamp": "2025-10-22 14:30:00"
  }
}
```

---

### POST /jobs/process

Process a batch of jobs from the queue.

**Parameters:**
- `batch_size` (int, optional, default: 10) - Number of jobs to process (1-100)
- `priority` (string, optional) - Filter by priority: `high`, `medium`, `normal`

**Request:**
```bash
curl -X POST https://yoursite.com/wp-json/msh/v1/jobs/process \
  -H "Authorization: Bearer YOUR_APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{
    "batch_size": 20,
    "priority": "high"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "processed": 15,
    "failed": 2,
    "skipped": 3,
    "message": "Processed 15 job(s), 2 failed."
  }
}
```

---

### GET /jobs/{id}

Get details for a specific job.

**Request:**
```bash
curl -X GET https://yoursite.com/wp-json/msh/v1/jobs/123 \
  -H "Authorization: Bearer YOUR_APP_PASSWORD"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "job": {
      "id": 123,
      "type": "regenerate_metadata",
      "entity_id": 2049,
      "status": "complete",
      "priority": "high",
      "payload": {
        "locale": "en_US",
        "field": "title"
      },
      "attempts": 1,
      "max_attempts": 3,
      "created_at": "2025-10-22 14:25:00",
      "started_at": "2025-10-22 14:26:15",
      "completed_at": "2025-10-22 14:26:45",
      "error_message": null
    }
  }
}
```

---

## Metadata Endpoints

### GET /metadata/cache

Get metadata cache entries with optional filtering.

**Parameters:**
- `media_id` (int, optional) - Filter by attachment ID
- `locale` (string, optional) - Filter by locale
- `limit` (int, optional, default: 50) - Number of entries to return

**Request:**
```bash
curl -X GET "https://yoursite.com/wp-json/msh/v1/metadata/cache?media_id=2049&locale=en_US" \
  -H "Authorization: Bearer YOUR_APP_PASSWORD"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "entries": [
      {
        "id": 1,
        "media_id": 2049,
        "locale": "en_US",
        "field": "title",
        "value": "Professional Physiotherapy Session",
        "source": "ai",
        "version_id": 45,
        "created_at": "2025-10-22 14:00:00",
        "updated_at": "2025-10-22 14:00:00"
      }
    ],
    "count": 1
  }
}
```

---

### POST /metadata/regenerate

Queue a metadata regeneration job for an attachment.

**Parameters:**
- `media_id` (int, required) - Attachment ID to regenerate
- `locale` (string, optional) - Specific locale to regenerate
- `field` (string, optional) - Specific field to regenerate (title, alt, caption, description)

**Request:**
```bash
curl -X POST https://yoursite.com/wp-json/msh/v1/metadata/regenerate \
  -H "Authorization: Bearer YOUR_APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{
    "media_id": 2049,
    "locale": "en_US",
    "field": "title"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "job_id": 124,
    "message": "Regeneration job created successfully."
  }
}
```

---

## Telemetry Endpoint

### POST /telemetry

Log a telemetry event (if telemetry is enabled).

**Parameters:**
- `event` (string, required) - Event name
- `data` (object, optional) - Event data

**Request:**
```bash
curl -X POST https://yoursite.com/wp-json/msh/v1/telemetry \
  -H "Authorization: Bearer YOUR_APP_PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "api_usage",
    "data": {
      "endpoint": "/metadata/regenerate",
      "duration_ms": 250
    }
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Telemetry event logged."
  }
}
```

---

## Error Responses

All endpoints return standard WordPress REST API error responses:

**Example Error:**
```json
{
  "code": "function_not_found",
  "message": "Job stats function not available.",
  "data": {
    "status": 500
  }
}
```

**Common Error Codes:**
- `rest_forbidden` - Authentication failed or insufficient permissions
- `function_not_found` - Required helper function not available
- `invalid_media_id` - Invalid attachment ID provided
- `job_not_found` - Job ID does not exist
- `job_creation_failed` - Failed to create job

---

## Usage Examples

### JavaScript (Frontend)

```javascript
// Get queue status
async function getQueueStatus() {
  const response = await fetch('/wp-json/msh/v1/jobs/status', {
    credentials: 'same-origin', // Use cookie authentication
    headers: {
      'Content-Type': 'application/json',
    },
  });
  const data = await response.json();
  console.log('Queue stats:', data.data.stats);
}

// Process 10 jobs
async function processJobs() {
  const response = await fetch('/wp-json/msh/v1/jobs/process', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      batch_size: 10,
      priority: 'high',
    }),
  });
  const data = await response.json();
  console.log('Processed:', data.data.processed);
}

// Regenerate metadata
async function regenerateMetadata(mediaId) {
  const response = await fetch('/wp-json/msh/v1/metadata/regenerate', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      media_id: mediaId,
      locale: 'en_US',
    }),
  });
  const data = await response.json();
  console.log('Job ID:', data.data.job_id);
}
```

### PHP (Plugin Integration)

```php
// Get queue status
$response = wp_remote_get(
  rest_url( 'msh/v1/jobs/status' ),
  array(
    'headers' => array(
      'Authorization' => 'Bearer ' . get_option( 'msh_api_key' ),
    ),
  )
);
$data = json_decode( wp_remote_retrieve_body( $response ), true );
$stats = $data['data']['stats'];

// Process jobs
$response = wp_remote_post(
  rest_url( 'msh/v1/jobs/process' ),
  array(
    'headers' => array(
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . get_option( 'msh_api_key' ),
    ),
    'body' => wp_json_encode( array(
      'batch_size' => 20,
    ) ),
  )
);

// Regenerate metadata
$response = wp_remote_post(
  rest_url( 'msh/v1/metadata/regenerate' ),
  array(
    'headers' => array(
      'Content-Type' => 'application/json',
    ),
    'body' => wp_json_encode( array(
      'media_id' => 2049,
      'locale' => 'en_US',
    ) ),
  )
);
```

### cURL (Command Line)

```bash
# Set up Application Password first in WordPress admin
# Then use Basic Auth with username:password

# Get queue status
curl -u admin:APP_PASSWORD \
  https://yoursite.com/wp-json/msh/v1/jobs/status

# Process 20 high-priority jobs
curl -u admin:APP_PASSWORD \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"batch_size": 20, "priority": "high"}' \
  https://yoursite.com/wp-json/msh/v1/jobs/process

# Regenerate metadata for image #2049
curl -u admin:APP_PASSWORD \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"media_id": 2049, "locale": "en_US"}' \
  https://yoursite.com/wp-json/msh/v1/metadata/regenerate
```

---

## Rate Limiting

Currently, there is no rate limiting on REST API endpoints. It's recommended to:
- Batch requests when possible
- Use webhooks/events instead of polling
- Cache responses where appropriate

Future versions may implement rate limiting for external integrations.

---

## Versioning

The API uses URL-based versioning (`/msh/v1/`). Breaking changes will result in a new version namespace (`/msh/v2/`), while backwards-compatible changes will be added to the existing version.

**Deprecation Policy:**
- Old versions supported for minimum 6 months after new version release
- Deprecation warnings added 3 months before removal
- Migration guides provided for all breaking changes

---

## Support

**Documentation:** https://github.com/toodokie/thedot-image-optimizer/blob/main/docs/
**Issues:** https://github.com/toodokie/thedot-image-optimizer/issues
**API Version:** v1 (introduced in v2.0.0)

---

**Last Updated:** October 22, 2025
