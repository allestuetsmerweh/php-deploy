# php-deploy
Deploy your PHP code, e.g. to a shared hosting. The only requirement on the deployment destination server is PHP and [the ZIP extension](https://www.php.net/manual/en/zip.setup.php).

## Filesystem structure on the deployment destination server

Definitions: 
- `$SECRET_ROOT`: The root directory for content not to be accessible via HTTP(S).
- `$PUBLIC_ROOT`: The root directory for content to be accessible via HTTP(S).
- `$DEPLOY_DIR`: The directory name for the deployments.
- `$RANDOM_DIR`: A random name for a directory, which does not exist yet in the parent directory.
- `$DATETIME`: The date & time of the deployment in the format `YYYY-MM-DD_hh_mm_ss`

Usages:
- `$PUBLIC_ROOT/$RANDOM_DIR/deploy.zip`: The zipped contents of the deployment.
- `$PUBLIC_ROOT/$RANDOM_DIR/deploy.php`: The script to unzip and install the deployment.
- `$SECRET_ROOT/$DEPLOY_DIR/$DATETIME/`: The directory to unzip the deployment to
