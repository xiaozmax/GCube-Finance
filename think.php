#!/usr/bin/env php
<?php

namespace think;
// 调试模式开关
define("APP_DEBUG", true);

// 定义CMF根目录,可更改此目录
define('CMF_ROOT', __DIR__ . '/');

// 定义CMF数据目录,可更改此目录
define('CMF_DATA', CMF_ROOT . 'data/');

// 定义网站入口目录
define('WEB_ROOT', __DIR__ . '/public/');

// 定义应用目录
define('APP_PATH', CMF_ROOT . 'app/');

// 定义缓存目录
define('RUNTIME_PATH', CMF_ROOT . 'data/runtime_cli/');

// 加载基础文件
require __DIR__ . '/vendor/thinkphp/base.php';

// 应用初始化
Container::get('app', [APP_PATH])->initialize();

// 控制台初始化
Console::init();
