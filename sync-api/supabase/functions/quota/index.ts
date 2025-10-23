// MSH Image Optimizer Sync - Quota Endpoint
// Purpose: Check remaining sync quota for license
// Method: GET /functions/v1/quota

import { serve } from 'https://deno.land/std@0.168.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type, x-license-key',
}

interface QuotaResponse {
  license_key: string
  plan: string
  quota: {
    monthly_limit: number
    used: number
    remaining: number
    percentage_used: number
    reset_date: string
  }
  sites: {
    current: number
    max: number
  }
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

    // Initialize Supabase client
    const supabase = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? ''
    )

    // 1. Get license details
    const { data: license, error: licenseError } = await supabase
      .from('licenses')
      .select('*')
      .eq('license_key', licenseKey)
      .single()

    if (licenseError || !license) {
      return new Response(
        JSON.stringify({
          error: {
            code: 'INVALID_LICENSE',
            message: 'License key not found',
          },
        }),
        {
          status: 403,
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        }
      )
    }

    // 2. Get current month quota usage
    const currentMonth = new Date().toISOString().slice(0, 7) // YYYY-MM

    const { data: quotaData } = await supabase
      .from('quota_usage')
      .select('operation_count')
      .eq('license_key', licenseKey)
      .eq('month', currentMonth)
      .single()

    const usedQuota = quotaData?.operation_count || 0
    const remaining = Math.max(0, license.quota_sync_ops_monthly - usedQuota)
    const percentageUsed = (usedQuota / license.quota_sync_ops_monthly) * 100

    // 3. Get site count
    const { count: siteCount } = await supabase
      .from('sites')
      .select('*', { count: 'exact', head: true })
      .eq('license_key', licenseKey)

    // 4. Calculate reset date (first day of next month)
    const now = new Date()
    const resetDate = new Date(now.getFullYear(), now.getMonth() + 1, 1)

    // 5. Return quota response
    const response: QuotaResponse = {
      license_key: licenseKey,
      plan: license.plan,
      quota: {
        monthly_limit: license.quota_sync_ops_monthly,
        used: usedQuota,
        remaining: remaining,
        percentage_used: Math.round(percentageUsed * 100) / 100,
        reset_date: resetDate.toISOString(),
      },
      sites: {
        current: siteCount || 0,
        max: license.max_sites,
      },
    }

    return new Response(JSON.stringify(response), {
      status: 200,
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    })
  } catch (error) {
    console.error('Quota error:', error)

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
