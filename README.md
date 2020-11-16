# baikal-birthday-calendar

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

This populates an existing calendar with birthday events from an existing address book.

(This intended for usage on your Baikal-Server, see: https://sabre.io/baikal/)

## Install

Via Composer

``` bash
$ composer require aburg/baikal-birthday-calendar
```

## Usage

``` php
$addressbookId = 1;
$birthdayCalendarId = 1;
$birthdayEventTitle = '%FULLNAME% has a birthday';

$bcm = new \Aburg\BaikalBirthdayCalendar\Service\BirthdayCalendarManager($birthdayEventTitle);
$bmc->updateBirthdayCalendar($addressbookId, $birthdayCalendarId);
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/aburg/baikal-birthday-calendar.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/aburg/baikal-birthday-calendar.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/aburg/baikal-birthday-calendar
[link-downloads]: https://packagist.org/packages/aburg/baikal-birthday-calendar
[link-author]: https://github.com/aburg
