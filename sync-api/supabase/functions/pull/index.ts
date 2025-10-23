// MSH Image Optimizer Sync - Pull Endpoint
// Purpose: Download remote metadata changes from cloud
// Method: POST /functions/v1/pull

import { serve } from 'https://deno.land/std@0.168.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type, x-license-key',
}

interface PullRequest {
  site_id: string
  since?: string // ISO 8601 timestamp
  limit?: number
  cursor?: string // For pagination
}

interface MetadataEntry {
  media_id: number
  locale: string
  title?: string
  alt?: string
  caption?: string
  description?: string
  custom?: Record<string, any>
  rev: number
  updated_at: string
}

interface PullResponse {
  changes: MetadataEntry[]
  next_cursor?: string
  has_more: boolean
}

serve(async (req) => {
  // Handle CORS preflight
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders })
  }

  try {
    // Get license key from header
    const licenseKey = req.headers.get('X-License-Key')

    if (!licenseKey) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'MISSING_LICENSE_KEY',
            message: 'X-License-Key header required',
          },
        }),
        {
          status: 401,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        }
      )
    }

    // Parse request body
    const body: PullRequest = await req.json()

    if (!body.site_id) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'INVALID_REQUEST',
            message: 'Missing required field: site_id',
          },
        }),
        {
          status: 400,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        }
      )
    }

    const limit = Math.min(body.limit || 100, 1000) // Max 1000 per request

    // Initialize Supabase client
    const supabase = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? ''
    )

    // 1. Verify license and site
    const { data: site, error: siteError } = await supabase
      .from('sites')
      .select('*, licenses(*)')
      .eq('site_id', body.site_id)
      .eq('license_key', licenseKey)
      .single()

    if (siteError || !site) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'INVALID_SITE',
            message: 'Site not found or license mismatch',
          },
        }),
        {
          status: 403,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        }
      )
    }

    // 2. Check quota
    const currentMonth = new Date().toISOString().slice(0, 7)

    const { data: quotaData } = await supabase
      .from('quota_usage')
      .select('operation_count')
      .eq('license_key', licenseKey)
      .eq('month', currentMonth)
      .single()

    const usedQuota = quotaData?.operation_count || 0

    if (usedQuota >= site.licenses.quota_sync_ops_monthly) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'QUOTA_EXCEEDED',
            message: `Monthly quota exceeded: ${usedQuota}/${site.licenses.quota_sync_ops_monthly}`,
          },
        }),
        {
          status: 402,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        }
      )
    }

    // 3. Get all sites under same license (for multi-site sync)
    const { data: licenseSites } = await supabase
      .from('sites')
      .select('site_id')
      .eq('license_key', licenseKey)

    const siteIds = licenseSites?.map((s) => s.site_id) || []

    // 4. Query metadata changes from OTHER sites under same license
    let query = supabase
      .from('media_metadata')
      .select('media_id, locale, title, alt, caption, description, custom, rev, updated_at')
      .in('site_id', siteIds)
      .neq('site_id', body.site_id) // Exclude own site
      .order('updated_at', { ascending: false })
      .limit(limit)

    // Filter by timestamp if provided
    if (body.since) {
      query = query.gte('updated_at', body.since)
    }

    // Cursor pagination
    if (body.cursor) {
      query = query.lt('updated_at', body.cursor)
    }

    const { data: changes, error: changesError } = await query

    if (changesError) {
      throw changesError
    }

    // 5. Determine if there are more results
    const hasMore = changes && changes.length === limit

    // 6. Log sync operation
    await supabase.from('sync_operations').insert({
      site_id: body.site_id,
      operation_type: 'pull',
      items_count: changes?.length || 0,
      conflicts_count: 0,
    })

    // 7. Update quota usage
    const newUsedQuota = usedQuota + 1

    await supabase.from('quota_usage').upsert(
      {
        license_key: licenseKey,
        month: currentMonth,
        operation_count: newUsedQuota,
      },
      {
        onConflict: 'license_key,month',
      }
    )

    // 8. Return response
    const response: PullResponse = {
      changes: changes || [],
      next_cursor: hasMore && changes && changes.length > 0
        ? changes[changes.length - 1].updated_at
        : undefined,
      has_more: hasMore,
    }

    return new Response(JSON.stringify(response), {
      status: 200,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    })
  } catch (error) {
    console.error('Pull error:', error)

    return new Response(
      JSON.stringify({
        error: {
          code: 'INTERNAL_ERROR',
          message: error.message || 'An unexpected error occurred',
        },
      }),
      {
        status: 500,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      }
    )
  }
})
