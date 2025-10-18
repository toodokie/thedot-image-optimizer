# MSH Image Optimizer - Translations

This directory contains translation files for the MSH Image Optimizer plugin.

## Files

- **msh-image-optimizer.pot** - Translation template (source for all translations)
- **msh-image-optimizer-{locale}.po** - Translation files for specific languages
- **msh-image-optimizer-{locale}.mo** - Compiled translation files (generated from .po files)

## Available Languages

### Core Languages (Initial Support)
- 🇪🇸 **Spanish (Spain)** - `es_ES`
- 🇫🇷 **French (France)** - `fr_FR`
- 🇩🇪 **German (Germany)** - `de_DE`
- 🇵🇹 **Portuguese (Portugal)** - `pt_PT`
- 🇮🇹 **Italian (Italy)** - `it_IT`

## Translation Statistics

**Total Translatable Strings:** ~900+
- Admin interface: ~500 strings
- Settings & forms: ~200 strings
- Error/success messages: ~100 strings
- Industry-specific templates: ~100 strings

## How to Translate

### Option 1: Using Poedit (Recommended for Translators)

1. **Download Poedit:** https://poedit.net/
2. **Open the `.po` file** for your language (e.g., `msh-image-optimizer-es_ES.po`)
3. **Translate the strings** in the Poedit interface
4. **Save** - Poedit automatically generates the `.mo` file
5. **Submit** your translated `.po` and `.mo` files

### Option 2: Using WP-CLI (For Developers)

```bash
# Edit translations in a .po file manually or with a tool
# Then compile to .mo format:
wp i18n make-mo languages/msh-image-optimizer-es_ES.po languages/

# Or compile all .po files at once:
wp i18n make-mo languages/
```

### Option 3: Manual Editing

1. Open `.po` file in any text editor
2. Find entries like:
   ```
   msgid "Analyze Images"
   msgstr ""
   ```
3. Add your translation:
   ```
   msgid "Analyze Images"
   msgstr "Analizar Imágenes"
   ```
4. Compile using WP-CLI or Poedit to generate `.mo` file

## Testing Translations

1. Place `.mo` file in `wp-content/languages/plugins/` or this directory
2. Change WordPress language in Settings → General
3. Check plugin admin interface for translated strings

## Translation Guidelines

### String Placeholders

Many strings contain placeholders like `%s`, `%d`, `%1$s`:

```php
sprintf(__('Processing %d images', 'msh-image-optimizer'), $count)
```

**Rules:**
- Keep placeholders in the same order
- Don't translate the placeholders themselves
- Example:
  ```
  msgid "Processing %d images"
  msgstr "Procesando %d imágenes"
  ```

### Context Comments

Some strings have translator comments for clarity:

```
#. translators: %1$s business name, %2$s industry
msgid "%1$s specializing in %2$s"
msgstr ""
```

Read these comments - they explain what the placeholders represent!

### HTML and Special Characters

- Keep HTML tags unchanged: `<strong>`, `<br>`, etc.
- Preserve special characters: `&nbsp;`, `&mdash;`
- Example:
  ```
  msgid "Click <strong>Save</strong> to continue"
  msgstr "Haga clic en <strong>Guardar</strong> para continuar"
  ```

## Contributing Translations

### For WordPress.org

Once the plugin is on WordPress.org, translations can be submitted at:
https://translate.wordpress.org/projects/wp-plugins/msh-image-optimizer/

### For GitHub

1. Fork the repository
2. Translate your language's `.po` file
3. Generate the `.mo` file
4. Submit a Pull Request with both files

## Translation Priority

### High Priority (User-Facing UI)
- ✅ Admin page headers & navigation
- ✅ Button text & actions
- ✅ Form labels & validation messages
- ✅ Success/error notifications

### Medium Priority (Secondary UI)
- Settings descriptions
- Help text & tooltips
- Modal dialogs

### Low Priority (Advanced/Technical)
- WP-CLI output (intentionally left in English)
- Debug log messages
- Developer-facing error codes

## Need Help?

- **WordPress Translation Handbook:** https://make.wordpress.org/polyglots/handbook/
- **Poedit Documentation:** https://poedit.net/trac/wiki/Doc
- **Plugin Support:** https://github.com/toodokie/thedot-image-optimizer/issues

---

**Last Updated:** October 17, 2025
**POT Version:** 1.2.0
**Total Strings:** 4,310 lines
