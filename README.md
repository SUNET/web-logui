Installation instructions for the web-logui application for the Halon MTA. Please read more on https://halon.io.

Requirements
---
* Halon MTA 5.3 or later
* PHP compatible web server (Apache, NGINX, IIS)
* PHP (>=7.1)
* [Composer](https://getcomposer.org)
* [Elasticsearch (>=6.x)](https://www.elastic.co/guide/en/elasticsearch/reference/current/install-elasticsearch.html)

Installation
---
1. Create a database file, for example, **web-logui.db**, outside of the document root, and give read and write access for the file to the web server's user.
2. Copy all project files to a web server directory, for example: /var/www/web-logui, and make sure that the site is configured on the chosen web server.
3. Make a copy of the `settings-default.php` file to `settings.php`, and edit the latter to configure all required settings.
- $ `cp ./settings-default.php ./settings.php`
- $ `vim ./settings.php`
4. Run the following commands to install the database and any dependencies from the project directory:
- $ `composer install`
- $ `php -f install.db.php`

Halon remote logging to Elasticsearch
---
In order to start logging to Elasticsearch, please see our [Remote logging to Elasticsearch](https://support.halon.io/hc/en-us/articles/115005513365) article.

Halon remote logging to Logstash
---

To enable the textlog feature, please see our [Remote syslog to Logstash](https://support.halon.io/hc/en-us/articles/360000700065) article.

Removing old indices
---

To remove old indices in Elasticsearch, you can use [Elasticsearch's Curator](https://www.elastic.co/guide/en/elasticsearch/client/curator/5.8/about.html) CLI as cron job.

This sample script will remove indices older than 120 days based on the index and date syntax (e.g. halon-%Y-%m-%d).

Tested with Curator 5.8.1.

~/.curator/curator.yml

```
client:
  hosts:
    - 127.0.0.1
  port: 9200
  url_prefix:
  use_ssl: True
  certificate:
  client_cert: '[...].crt'
  client_key: '[...].key'
  ssl_no_validate: True
  http_auth: 'user:badpassword'
  timeout: 30
  master_only: False

logging:
  loglevel: INFO
  logfile:
  logformat: default
  blacklist: ['elasticsearch', 'urllib3']
```

action_file_name.yml

```
actions:
  1:
    action: delete_indices
    description: >-
      Delete indices older than 120 days (based on index name)
    options:
      ignore_empty_list: True
      disable_action: False
    filters:
    - filtertype: pattern
      kind: prefix
      value: halon-
    - filtertype: age
      source: name
      direction: older
      timestring: '%Y-%m-%d'
      unit: days
      unit_count: 120
```

Run the action file with this command `curator action_file_name.yml`.
