// MSH Image Optimizer Sync - Push Endpoint
// Purpose: Upload local metadata changes to cloud
// Method: POST /functions/v1/push

import { serve } from 'https://deno.land/std@0.168.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type, x-license-key',
}

interface MetadataChange {
  media_id: number
  locale: string
  title?: string
  alt?: string
  caption?: string
  description?: string
  custom?: Record<string, any>
  rev?: number
}

interface PushRequest {
  site_id: string
  changes: MetadataChange[]
}

interface PushResponse {
  pushed: number
  conflicts: Array<{
    media_id: number
    locale: string
    cloud_rev: number
    local_rev: number
  }>
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
    const body: PushRequest = await req.json()

    if (!body.site_id || !body.changes || !Array.isArray(body.changes)) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'INVALID_REQUEST',
            message: 'Missing required fields: site_id, changes',
          },
        }),
        {
          status: 400,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        }
      )
    }

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

    // 3. Process each change with conflict detection
    const pushed: MetadataChange[] = []
    const conflicts: PushResponse['conflicts'] = []

    for (const change of body.changes) {
      // Check for existing metadata
      const { data: existing } = await supabase
        .from('media_metadata')
        .select('rev')
        .eq('site_id', body.site_id)
        .eq('media_id', change.media_id)
        .eq('locale', change.locale)
        .single()

      // Conflict detection (optimistic locking)
      if (existing && change.rev && existing.rev !== change.rev) {
        conflicts.push({
          media_id: change.media_id,
          locale: change.locale,
          cloud_rev: existing.rev,
          local_rev: change.rev,
        })
        continue
      }

      // Upsert metadata
      const { error: upsertError } = await supabase.from('media_metadata').upsert(
        {
          site_id: body.site_id,
          media_id: change.media_id,
          locale: change.locale,
          title: change.title,
          alt: change.alt,
          caption: change.caption,
          description: change.description,
          custom: change.custom || {},
          storage: null, // AVIF placeholder (Phase 10)
          rev: existing ? existing.rev + 1 : 1,
          updated_at: new Date().toISOString(),
        },
        {
          onConflict: 'site_id,media_id,locale',
        }
      )

      if (!upsertError) {
        pushed.push(change)
      }
    }

    // 4. Log sync operation
    await supabase.from('sync_operations').insert({
      site_id: body.site_id,
      operation_type: 'push',
      items_count: pushed.length,
      conflicts_count: conflicts.length,
    })

    // 5. Update quota usage
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

    // 6. Return response
    const response: PushResponse = {
      pushed: pushed.length,
      conflicts: conflicts,
    }

    return new Response(JSON.stringify(response), {
      status: 200,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    })
  } catch (error) {
    console.error('Push error:', error)

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
