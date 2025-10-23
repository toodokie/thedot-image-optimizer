// MSH Image Optimizer Sync - Resolve Endpoint
// Purpose: Resolve sync conflicts with strategy selection
// Method: POST /functions/v1/resolve

import { serve } from 'https://deno.land/std@0.168.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type, x-license-key',
}

interface ConflictResolution {
  media_id: number
  locale: string
  strategy: 'remote_wins' | 'local_wins' | 'newest_wins' | 'manual'
  manual_value?: {
    title?: string
    alt?: string
    caption?: string
    description?: string
    custom?: Record<string, any>
  }
}

interface ResolveRequest {
  site_id: string
  resolutions: ConflictResolution[]
}

interface ResolveResponse {
  resolved: number
  failed: Array<{
    media_id: number
    locale: string
    reason: string
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
    const body: ResolveRequest = await req.json()

    if (!body.site_id || !body.resolutions || !Array.isArray(body.resolutions)) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'INVALID_REQUEST',
            message: 'Missing required fields: site_id, resolutions',
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
      .select('*')
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

    // 2. Process each resolution
    const resolved: ConflictResolution[] = []
    const failed: ResolveResponse['failed'] = []

    for (const resolution of body.resolutions) {
      try {
        // Get current cloud metadata
        const { data: cloudMeta } = await supabase
          .from('media_metadata')
          .select('*')
          .eq('site_id', body.site_id)
          .eq('media_id', resolution.media_id)
          .eq('locale', resolution.locale)
          .single()

        if (!cloudMeta) {
          failed.push({
            media_id: resolution.media_id,
            locale: resolution.locale,
            reason: 'Metadata not found in cloud',
          })
          continue
        }

        let finalValue: any

        // Apply strategy
        switch (resolution.strategy) {
          case 'remote_wins':
            // Keep cloud value (no update needed)
            resolved.push(resolution)
            continue

          case 'local_wins':
            // Client should push their local value (return success to proceed)
            resolved.push(resolution)
            continue

          case 'manual':
            if (!resolution.manual_value) {
              failed.push({
                media_id: resolution.media_id,
                locale: resolution.locale,
                reason: 'Manual strategy requires manual_value',
              })
              continue
            }
            finalValue = resolution.manual_value
            break

          case 'newest_wins':
            // Client should send both timestamps - for now treat as remote_wins
            resolved.push(resolution)
            continue

          default:
            failed.push({
              media_id: resolution.media_id,
              locale: resolution.locale,
              reason: `Unknown strategy: ${resolution.strategy}`,
            })
            continue
        }

        // Update if manual strategy
        if (resolution.strategy === 'manual' && finalValue) {
          await supabase
            .from('media_metadata')
            .update({
              ...finalValue,
              rev: cloudMeta.rev + 1,
              updated_at: new Date().toISOString(),
            })
            .eq('site_id', body.site_id)
            .eq('media_id', resolution.media_id)
            .eq('locale', resolution.locale)

          resolved.push(resolution)
        }
      } catch (error) {
        failed.push({
          media_id: resolution.media_id,
          locale: resolution.locale,
          reason: error.message || 'Unknown error',
        })
      }
    }

    // 3. Log resolution operation
    await supabase.from('sync_operations').insert({
      site_id: body.site_id,
      operation_type: 'resolve',
      items_count: resolved.length,
      conflicts_count: failed.length,
    })

    // 4. Return response
    const response: ResolveResponse = {
      resolved: resolved.length,
      failed: failed,
    }

    return new Response(JSON.stringify(response), {
      status: 200,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    })
  } catch (error) {
    console.error('Resolve error:', error)

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
