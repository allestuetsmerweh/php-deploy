# php-deploy
Deploy your PHP code, e.g. to a shared hosting.

The only requirement on the deployment destination server is PHP and [the ZIP extension](https://www.php.net/manual/en/zip.setup.php).

## Usage

- `composer require allestuetsmerweh/php-deploy`
- Create a file `Deploy.php`, containing a class implementing `AbstractDefaultDeploy` (or `AbstractDeploy`)
- Run `PASSWORD=$DEPLOY_PASSWORD php ./Deploy.php --environment=prod --username=$DEPLOY_USERNAME` to deploy

## Filesystem structure on the deployment destination server

Definitions: 
- `$SECRET_ROOT`: The root directory for content not to be accessible via HTTP(S).
- `$PUBLIC_ROOT`: The root directory for content to be accessible via HTTP(S).
- `$DEPLOY_DIR`: The directory name for the deployments. Default: `deploy`.
- `$RANDOM_DIR`: A random name for a directory, which does not exist yet in the parent directory.

Usages:
- `$PUBLIC_ROOT/$RANDOM_DIR/deploy.zip`: The zipped contents of the deployment.
- `$PUBLIC_ROOT/$RANDOM_DIR/deploy.php`: The script to unzip and install the deployment.
- `$SECRET_ROOT/$DEPLOY_DIR/candidate/`: The directory to unzip the deployment to
- `$SECRET_ROOT/$DEPLOY_DIR/live/`: The directory where the current deployment is stored
- `$SECRET_ROOT/$DEPLOY_DIR/previous/`: The directory where the previous deployment is stored

## CI on github.com

Example `.github/workflows/deploy-prod.yml`:

```
on:
  push:
    branches:
      - main
name: Deploy:prod
jobs:
  # TODO: Tests

  deploy-prod:
    name: Deploy to my-domain.com
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v2
    - uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
    - name: Get composer cache directory
      id: composer-cache
      run: echo "::set-output name=dir::$(composer config cache-files-dir)"
    - name: Cache dependencies
      uses: actions/cache@v1
      with:
        path: ${{ steps.composer-cache.outputs.dir }}
        key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
        restore-keys: ${{ runner.os }}-composer-
    - name: Install dependencies
      run: composer install --prefer-dist
    - name: Deploy
      env:
        USERNAME: ${{ secrets.DEPLOY_USERNAME }}
        PASSWORD: ${{ secrets.DEPLOY_PASSWORD }}
      run: php ./Deploy.php --environment=prod --username="$USERNAME"
```

## CI on bitbucket.org

Example `bitbucket-pipelines.yml`:

```
image: php:8.1.1
pipelines:
  default:
    - parallel:
      - step:
          name: 'Build and Test'
          script:
            - echo "Your build and test goes here..."
      - step:
          name: 'Lint'
          script:
            - echo "Your linting goes here..."
      - step:
          name: 'Security scan'
          script:
            - echo "Your security scan goes here..."

    - step:
        name: 'Deployment to Production'
        deployment: production
        trigger: 'manual'
        script:
          - apt-get update && apt-get install -y libzip-dev
          - docker-php-ext-install zip
          - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
          - composer install
          - PASSWORD=$DEPLOY_PASSWORD php ./Deploy.php --environment=prod --username=$DEPLOY_USERNAME
```
