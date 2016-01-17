# Google Cache Site Recover - v0.4 Alpha

This script is a way to recover a **static version** of your site if you are
out of luck and have no recent backups. Take a list of URLs of your site,
put in a file (default `urls.txt`) without domain name, and execute
`./gcsr.php http://www.site.com` and it will by default save from Google Cache,
remove Google Cache information header. Also, it will try to recover Images, CSS
and JS from original host.

It will rebuild your site with the same folder structure. In case you have URLs
without one extension, will add .html because without this, your OS will not
allow create deeper links

## How fast is?

By Default, will make requests to Google Cache each 63 to 70 seconds. At time
of this documentation, for **more than 59 pages Google WILL block your IP for
~8h if you try more faster**.

Proxy requests still not implemented

## Requeriments

- PHP 5.4 (with php5-curl, very common)
- command line access

## Why?

The initial version of this script was done for a very big website, with no
recent backup, but some static files (CSS, JS, Imagens) still on a reverse
proxy (CloudFlare).

Also, this keep in mind that use of this service is againts Google ToS.
