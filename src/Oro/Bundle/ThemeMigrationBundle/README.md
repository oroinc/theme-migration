# Theme migration command

This command will extract from a given application all the layout files related to 'default' theme and
combine everything into a single theme bundle.

To execute the extraction/migration run

```shell
php bin/console oro:theme:migrate
```

The logic of the extraction is the following:
- Create new OroThemeDefaultXXBundle, where XX is the current app version
- Empty the Resources/views & Resources/public folders in OroThemeDefaultXXBundle if exists
- Read the theme layout resource configuration and parse it, start copying all the yml files which are references there.
    - For each yml file parse it's content:
        - copy the file into the new bundle, name it by prefixing every file with a bundle priority number, and suffixing with bundle alias
        - recursively search for referenced other yml/twig files, following the same naming principle
- search for other yml files (assets.yml, theme.yml, jsmodules.yml etc, copy and parse them searching for other references)
- search for css files, copy and parse them
- recursively do the same extraction for the parent of `default` theme, if there is one.
