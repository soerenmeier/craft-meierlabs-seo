# How to Setup a Development Environment

Create or modify the following file to add the dev folder to the ddev container:
`.ddev/docker-compose.mounts.yaml`
```
services:
  web:
    volumes:
      - "$HOME/devdrive/craft-meierlabs-seo:/home/craft-meierlabs-seo"
```

Run `ddev restart` to apply the changes.

Modify the `composer.json` to add the local path:
```
"repositories": [
    {
        "type": "path",
        "url": "/home/craft-meierlabs-seo"
    }
]
```

and change the require section to:
```
"require": {
    "soerenmeier/meierlabs-seo": "dev-main",
}
```

Then run `ddev composer update soerenmeier/meierlabs-seo` to install the local version of the plugin.

## npm package

The crelte package is in `packages/meierlabs-seo`. Run `npm install` and
`npm run watch` there, and link it into the frontend with
`npm install ../path/to/craft-meierlabs-seo/packages/meierlabs-seo`, or test
the published form with `npm pack` and installing the tarball.

Publish with `npm publish` in `packages/meierlabs-seo` (builds via `prepack`).
