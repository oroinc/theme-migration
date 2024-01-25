# Oro Theme Migration Bundle

This bundle includes a command designed to extract all layout files associated with the `default` theme from the application into a single theme migration bundle. 
This facilitates the reuse of the theme in OroCommerce 6.0, minimizing the need for extensive theme modifications during the upgrade process.	

To initiate the theme extraction and migration, execute the following commands:

```shell
rm -rf public/bundles
php bin/console assets:install
php bin/console oro:theme:migrate
```
The extraction process is based on the following logic:

- Dump the assets into the `public/bundles` folder to enable their reuse during the theme migration.
- Create a new `OroThemeDefaultXXBundle`, where XX represents the current version of the application.
- Clear out the `Resources/views` and `Resources/public` folders (if they exist) in `OroThemeDefaultXXBundle`.
- Analyze the theme layout resource configuration and commence the copying of all referenced YAML files.
- For each YAML file:  
  - Transfer the file to the new bundle, renaming it to include a bundle priority number as a prefix and the bundle alias as a suffix.
  - Recursively identify and follow references to other YAML/Twig files, applying the same naming convention.
  - Locate and process additional YAML files (e.g., `assets.yml,` `theme.yml,` `jsmodules.yml`), parsing them for further reference.
  - Search for and copy CSS files, parsing them similarly.
  - Repeat the extraction process for the parent of the default theme if one exists.
