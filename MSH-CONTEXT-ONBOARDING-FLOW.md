# MSH Image Optimizer - Context Onboarding Flow

## Complete Field List (Settings > Context Tab)

### Primary Context Form

The onboarding form collects 14 fields in the following order:

#### 1. **Business Name** (required)
- **Field Type**: Text input
- **Example**: Main Street Health
- **Purpose**: Primary business identifier used throughout metadata generation

#### 2. **Industry** (required)
- **Field Type**: Dropdown select
- **Options**: Healthcare, Technology, Retail, etc.
- **Example**: Healthcare
- **Purpose**: Industry classification for context-aware recommendations

#### 3. **Business Type** (required)
- **Field Type**: Dropdown select
- **Options**: Service-Based Business, Product-Based Business, etc.
- **Example**: Service-Based Business
- **Purpose**: Business model classification

#### 4. **Ideal Customer** (required)
- **Field Type**: Text input
- **Placeholder**: e.g., SaaS founders and B2B marketing leads launching demand-gen campaigns
- **Example**: First responders including paramedics, police officers, firefighters, and workplace injury patients seeking rehabilitation and occupational health services
- **Purpose**: Target audience definition for SEO and content optimization

#### 5. **Problems You Solve** (optional)
- **Field Type**: Textarea
- **Placeholder**: e.g., Clarifying positioning, building conversion-focused landing pages…
- **Example**: Treating work-related injuries, chronic pain management, post-operative rehabilitation, return-to-work assessments, concussion management, and specialized care for first responders and veterans
- **Purpose**: Core value proposition and service description

#### 6. **Demographics** (optional)
- **Field Type**: Text input
- **Placeholder**: e.g., VC-backed teams, 10–100 employees, North American tech hubs
- **Example**: Adults aged 25-65, primarily employed professionals, first responders, corporate employees, located in Greater Hamilton Area, Ontario
- **Purpose**: Detailed audience segmentation

#### 7. **Brand Voice** (required)
- **Field Type**: Radio button group
- **Options**: Professional, Friendly, Technical, Casual, etc.
- **Example**: Professional
- **Purpose**: Tone and style guidance for generated metadata

#### 8. **What Makes You Different?** (required)
- **Field Type**: Textarea
- **Placeholder**: Highlight differentiators, proof points, or signature offers.
- **Example**: Specialized first responder program with rapid physician referral system, multi-disciplinary approach combining physiotherapy, chiropractic, and massage therapy, advanced gait scan technology, comprehensive functional abilities evaluations, and dedicated Veterans Affairs services
- **Purpose**: Unique value propositions (UVPs) for competitive differentiation

#### 9. **Call-to-Action Style** (optional)
- **Field Type**: Dropdown select
- **Options**: Direct, Soft, etc.
- **Example**: Direct
- **Purpose**: CTA tone preference for metadata recommendations

### Location Fields

#### 10. **City**
- **Field Type**: Text input
- **Example**: Hamilton
- **Purpose**: Primary city for local SEO

#### 11. **Province / Region**
- **Field Type**: Text input
- **Example**: Ontario
- **Purpose**: State/province for regional SEO

#### 12. **Country**
- **Field Type**: Text input
- **Example**: Canada
- **Purpose**: Country for international SEO

#### 13. **Service Area**
- **Field Type**: Text input
- **Placeholder**: e.g., Remote across North America
- **Example**: Greater Hamilton Area including Ancaster, Dundas, Stoney Creek, Burlington, and Grimsby
- **Purpose**: Geographic service coverage for location-based metadata

### Preferences

#### 14. **Subscribe to AI Feature Updates**
- **Field Type**: Checkbox
- **Default**: Unchecked
- **Purpose**: Marketing opt-in for AI feature announcements

---

## Complete Example: Main Street Health

```
Business Name: Main Street Health

Industry: Healthcare

Business Type: Service-Based Business

Ideal Customer: First responders including paramedics, police officers, firefighters, and workplace injury patients seeking rehabilitation and occupational health services

Problems You Solve: Treating work-related injuries, chronic pain management, post-operative rehabilitation, return-to-work assessments, concussion management, and specialized care for first responders and veterans

Demographics: Adults aged 25-65, primarily employed professionals, first responders, corporate employees, located in Greater Hamilton Area, Ontario

Brand Voice: Professional

What Makes You Different?: Specialized first responder program with rapid physician referral system, multi-disciplinary approach combining physiotherapy, chiropractic, and massage therapy, advanced gait scan technology, comprehensive functional abilities evaluations, and dedicated Veterans Affairs services

Call-to-Action Style: Direct

City: Hamilton

Province / Region: Ontario

Country: Canada

Service Area: Greater Hamilton Area including Ancaster, Dundas, Stoney Creek, Burlington, and Grimsby

Subscribe to AI Feature Updates: ✓ Checked
```

---

## Context Usage in Plugin

### Where Context is Used

1. **Image Metadata Generation**
   - Alt text recommendations
   - Title suggestions
   - Description generation
   - Caption creation

2. **AI Service Integration**
   - Prompt enhancement with business context
   - Industry-specific terminology
   - Location-aware SEO keywords

3. **Template System**
   - Context-aware template variables
   - Business-specific placeholders
   - Audience-targeted content

4. **Batch Operations**
   - Cached context for performance (Phase 1F)
   - Single context load per batch operation
   - Reduced wp_options queries

### Storage

- **Primary Context**: `msh_onboarding_context` wp_option
- **Context Profiles**: `msh_onboarding_context_profiles` wp_option (multi-profile support)
- **Active Profile**: `msh_active_context_profile` wp_option

### Caching

**Phase 1F Implementation**: Context is loaded once at batch start and cached in memory:
```php
// At batch start:
$context = get_option('msh_onboarding_context');
// Cached in class property, no subsequent wp_options queries during batch loop
```

---

## Required vs Optional Fields

### Required Fields (Cannot Submit Without)
1. Business Name
2. Industry
3. Business Type
4. Ideal Customer
5. Brand Voice
6. What Makes You Different?

### Optional Fields (Can Be Left Empty)
1. Problems You Solve
2. Demographics
3. Call-to-Action Style
4. City
5. Province / Region
6. Country
7. Service Area
8. Subscribe to AI Feature Updates

---

## Access in WordPress

**Navigation**: WordPress Admin > TinyDot > Settings > Context Tab

**Permissions**: Requires `manage_options` capability (Administrator role)

---

## Technical Notes

- Form uses `admin-post.php` action handler
- Nonce verification: `msh_save_context_settings`
- Data sanitization on save
- Multi-profile support available (not covered in basic onboarding)
- Context can be updated anytime without affecting existing metadata
- Changes apply to future metadata generation operations

---

*Document Version: 1.0*
*Last Updated: 2025-10-28*
*Plugin Version: v1.2.7+*
