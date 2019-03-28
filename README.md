# Elastic APM

Provides integration with the PHP agent for Elastic's APM product (https://github.com/philkra/elastic-apm-php-agent).

Currently, the module page on d.org is here: https://www.drupal.org/sandbox/shabanablackborder/3034968

## Module Setup

- Make sure to include the following in your composer.json 'repositories' array:

```
{
  "name": "drupal/elastic_apm",
  "type": "vcs",
  "url": "https://github.com/shabananavas/elastic_apm.git"
},
```

- Install the module via composer `composer require drupal/elastic_apm:dev-develop`

<strong>Until the current PRs are merged, the 'develop' version should be used to see the module in full action.</strong>
