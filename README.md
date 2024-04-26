# Oro Theme Migration Bundle

This bundle includes a command designed to extract all layout files associated with the `default` theme from the application into a single theme migration bundle. 
This facilitates the reuse of the theme in OroCommerce 6.0, minimizing the need for extensive theme modifications during the upgrade process.

### To initiate the theme extraction and migration:

1. Check out the old version of the application (XX represents the old version of the app)

2. Execute the following commands:
```shell
rm -rf public/bundles
php bin/console assets:install
php bin/console oro:theme:migrate
```

3. Copy `OroThemeDefaultXXBundle` from the old version of the application to the new one

4. Check out the new version of the application

5. Enable the new theme by editing the `config.yml` file
```yaml
...
oro_layout:
    enabled_themes:
        - default_XX
...
```

6. Update the `theme.yml` file of the custom theme
```yaml
...
parent: default_XX
...
resolve_extra_paths:
  - /bundles/orothemedefaultXX
...
```

7. Manually move the custom storefront datagrids from 
`<AnyBundle>/Resources/config/oro/datagrids.yml` to 
`<AnyBundle>/Resources/views/layouts/<themeid>/config/datagrids.yml`

8. Remove old caches. E.g.
```shell
rm -rf var/cache/prod/
```

9. Publish the new files into the public folder
```shell
oro:assets:install (--symlink)
```

-----

### The extraction process follows this logic:
- Dump the assets into the `public/bundles` folder to enable their reuse during the theme migration.
- Create a new `OroThemeDefaultXXBundle`, where XX represents the current version of the app.
- If it exists, clear out the `Resources/views` and `Resources/public` folders in `OroThemeDefaultXXBundle`.
- Analyze the theme layout resource configuration and commence the copying of all referenced YAML files.
  - For each YAML file:
    - Transfer the file to the new bundle, renaming it to include a bundle priority number as a prefix and the bundle alias as a suffix.
    - Recursively identify and follow references to other YAML/Twig files, applying the same naming convention.
  - Locate and process additional YAML files (e.g., `assets.yml`, `theme.yml`, `jsmodules.yml`), parsing them for further references.
  - Search for and copy CSS files, parsing them similarly.
  - Repeat the extraction process for the parent of the default theme, if one exists.
