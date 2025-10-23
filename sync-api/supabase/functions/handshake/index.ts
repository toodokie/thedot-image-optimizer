// MSH Image Optimizer Sync - Handshake Endpoint
// Purpose: Register/update site and verify license
// Method: POST /functions/v1/handshake

import { serve } from 'https://deno.land/std@0.168.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
}

interface HandshakeRequest {
  license_key: string
  url: string
  platform?: 'wordpress' | 'shopify' | 'webflow' | 'api'
  plugin_version: string
  wp_version?: string
  capabilities?: string[]
}

interface HandshakeResponse {
  site_id: string
  license: {
    plan: string
    status: string
    max_sites: number
    quota_remaining: number
  }
  message?: string
}

serve(async (req) => {
  // Handle CORS preflight
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders })
  }

  try {
    // Parse request body
    const body: HandshakeRequest = await req.json()

    // Validate required fields
    if (!body.license_key || !body.url || !body.plugin_version) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'INVALID_REQUEST',
            message: 'Missing required fields: license_key, url, plugin_version',
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

    // 1. Validate license
    const { data: license, error: licenseError } = await supabase
      .from('licenses')
      .select('*')
      .eq('license_key', body.license_key)
      .single()

    if (licenseError || !license) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'INVALID_LICENSE',
            message: 'License key not found or invalid',
          },
        }),
        {
          status: 403,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        }
      )
    }

    // Check license status
    if (license.status !== 'active') {
      return new Response(
        JSON.stringify({
          error: {
            code: 'LICENSE_INACTIVE',
            message: `License status: ${license.status}`,
          },
        }),
        {
          status: 403,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        }
      )
    }

    // Check if license expired
    if (license.expires_at && new Date(license.expires_at) < new Date()) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'LICENSE_EXPIRED',
            message: 'License has expired',
          },
        }),
        {
          status: 403,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        }
      )
    }

    // 2. Check site limit
    const { count: siteCount } = await supabase
      .from('sites')
      .select('*', { count: 'exact', head: true })
      .eq('license_key', body.license_key)

    // 3. Register or update site
    const { data: site, error: siteError } = await supabase
      .from('sites')
      .upsert(
        {
          license_key: body.license_key,
          url: body.url,
          platform: body.platform || 'wordpress',
          wp_version: body.wp_version,
          plugin_version: body.plugin_version,
          capabilities: body.capabilities || [],
          last_handshake_at: new Date().toISOString(),
        },
        {
          onConflict: 'license_key,url',
        }
      )
      .select('site_id')
      .single()

    if (siteError) {
      // Check if we hit max_sites limit
      if (siteError.code === '23505' && siteCount && siteCount >= license.max_sites) {
        return new Response(
          JSON.stringify({
            error: {
              code: 'MAX_SITES_EXCEEDED',
              message: `License allows maximum ${license.max_sites} sites. Current: ${siteCount}`,
            },
          }),
          {
            status: 403,
            headers: { ...corsHeaders, 'Content-Type': 'application/json' },
          }
        )
      }

      throw siteError
    }

    // 4. Get quota usage for current month
    const currentMonth = new Date().toISOString().slice(0, 7) // YYYY-MM

    const { data: quotaData } = await supabase
      .from('quota_usage')
      .select('operation_count')
      .eq('license_key', body.license_key)
      .eq('month', currentMonth)
      .single()

    const usedQuota = quotaData?.operation_count || 0
    const quotaRemaining = Math.max(0, license.quota_sync_ops_monthly - usedQuota)

    // 5. Return success response
    const response: HandshakeResponse = {
      site_id: site.site_id,
      license: {
        plan: license.plan,
        status: license.status,
        max_sites: license.max_sites,
        quota_remaining: quotaRemaining,
      },
      message: siteCount === 0 ? 'Site registered successfully' : 'Site updated successfully',
    }

    return new Response(JSON.stringify(response), {
      status: 200,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    })
  } catch (error) {
    console.error('Handshake error:', error)

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
