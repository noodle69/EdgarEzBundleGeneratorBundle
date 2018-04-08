# EdgarEzBundleGeneratorBundle

## Command

example :

```
php bin/console edgarez:generate:bundle --namespace=Acme/EzFooBundle
```

After generating new eZ Platform Bundle, a notification inform to modify your composer.json to add new bundle to composer autoload.

So, edit your composer.json and add following lines to "autoload" "psr-4" sectioon:

```json
{
    "name": "ezsystems/ezplatform",
    ...
    "autoload": {
        "psr-4": {
            "AppBundle\\": "src/AppBundle/",
            ...
            "<Vendor>\\<EzBundleBundle>\\": "src/<Vendor>/<EzBundleBundle>/src/bundle/",
            "<Vendor>\\<EzBundle>\\": "src/<Vendor>/<EzBundleBundle>/src/lib/",
            ...
        },
        "classmap": [ "app/AppKernel.php", "app/AppCache.php" ]
    },
    ...
}
```

where :
* Vendor: your vendor folder name
* EzBundleBundle: your Ez Platform Bundle folder name (EzFooBundle)
* EzBundle: your Ez Platform Bundle name (EzFoo)

example :

```json
{
    "name": "ezsystems/ezplatform",
    ...
    "autoload": {
        "psr-4": {
            "AppBundle\\": "src/AppBundle/",
            ...
            "Acme\\EzFooBundle\\": "src/Edgar/EzFooBundle/src/bundle/",
            "Acme\\EzFoo\\": "src/Edgar/EzFooBundle/src/lib/",
            ...
        },
        "classmap": [ "app/AppKernel.php", "app/AppCache.php" ]
    },
    ...
}
```

After all, you should dump composer autoload by executing this command :

```
composer dumpautoload
```

### Options

* with-security : Generate PolicyProvider and policies.yml
* platform-ui : Generate controller/routing for an eZ Platform Admin UI bundle
