# PyroSpy
Adapter from [phpspy](https://github.com/adsr/phpspy) to [pyroscope.io](https://pyroscope.io)

## About Us
<table width="100%" border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td>
      <a href="https://company.zoon.ru">
        <img src="https://company.zoon.ru/images/logo.svg" width="140" alt="zoon logo"/>
      </a>
    </td>
    <td>
        <b><a href="https://zoon.ru/" target="_blank">Zoon</a></b> - it's international service, that helps local businesses grow.
        <ul>
            <li>We tell the audience about the services and products of companies</li>
            <li>We promote on dozens of sites: in the catalog on zoon.ru, on partner sites, on Yandex and Google maps</li>
            <li>We help business owners manage marketing from a single personal account</li>
        </ul>
    </td>
  </tr>
</table>

## Parameters:
```text
Usage:
  php pyrospy.php run [options]

Options:
  -s, --pyroscope=STRING     Url of the pyroscope server. 
                             Example: https://your-pyroscope-sever.com
                             
  -a, --app=STRING           Name of app. 
                             All samples will be saved under given app name.
                             Example: app
                             
  -r, --rateHz=INT           Sample rate in Hz. 
                             Used to convert number of samples to CPU time 
                             [default: 100]
                             
  -i, --interval=INT         Maximum time between requests to pyroscope server 
                             [default: 10]
                             
  -b, --batch=INT            Maximum number of traces in request to pyroscope server 
                             [default: 250]
                             
  -t, --tags=STRING=STRING   Add tags to samples. Use to filter data inside one app.
                             Example: host=server1; role=cli 
                             (multiple values allowed)
                             
  -p, --plugins=STRING       Load custom class to modify trace and phpspy comments/tags. Can be class or folder with classes.
                             Example: /zoon/pyrospy/app/Plugins/ClearEmptyTags.php
                             (multiple values allowed)
                             
  -h, --help                 Display help for the given command. 
                             When no command is given display help for the list command

```

## Usage:
```shell
phpspy --max-depth=-1 --time-limit-ms=59000 --threads=1024 --rate-hz=4 --buffer-size=65536 -P '-x "php|php[0-9]\.[0-9]" | shuf' 2> error_log.log | php pyrospy.php run --pyroscope=https://pyroscope.yourdomain.com --rateHz=4 --app=testApp --tags=host=server39 --tags=role=cli

phpspy --max-depth=-1 --time-limit-ms=59000 --threads=100 --rate-hz=25 --buffer-size=65536 -P '-x "php-fpm|php-fpm[0-9]\.[0-9]" | shuf' 2> error_log.log | php pyrospy.php run --pyroscope=https://pyroscope.yourdomain.com --rateHz=25 --app=testApp --tags=host=server39 --tags=role=web
```

## Plugins

1. Create `.php` plugin class. Put it in any place. Make sure it has `namespace Zoon\PyroSpy\Plugins;` and classname match filename.
```php
<?php

namespace Zoon\PyroSpy\Plugins;

class MyAvesomePlugin implements PluginInterface {

    public function process(array $tags, array $trace): array {
        //Modify tags and/or trace

        return [$tags, $trace];
    }
}
```
Multiple plugins can be provided. Each plugin will get tags and trace from results of the previous.

2. Add `--request-info=QCuP` to phpspy args, to add uri string to tags.
3. Provide full path to it in pyrospy arguments.

Example:
```shell
phpspy --max-depth=-1 --time-limit-ms=59000 --threads=100 --rate-hz=25 --buffer-size=65536 -P '-x "php-fpm|php-fpm[0-9]\.[0-9]" | shuf' --request-info=QCuP 2> error_log.log | php pyrospy.php run --pyroscope=https://pyroscope.yourdomain.com --rateHz=25 --app=testApp --tags=host=server39 --tags=role=web --plugins=/zoon/pyrospy/app/Plugins/ClearEmptyTags.php
```