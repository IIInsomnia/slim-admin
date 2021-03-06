<?php
// DIC configuration
$container = $app->getContainer();

// slim/twig-view
$container['view'] = function($c) {
    $view = new \Slim\Views\Twig(__DIR__ . '/../views', [
        'cache' => false,
    ]);

    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $c['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($c['router'], $basePath));

    return $view;
};

// Illuminate/database
$container['db'] = function($c) {
    $connections = $c->get('settings')['db'];

    $capsule = new \Illuminate\Database\Capsule\Manager;

    foreach ($connections as $name => $config) {
        $capsule->addConnection($config, $name);
    }

    $capsule->setAsGlobal();

    return $capsule;
};

// MongoDB
$container['mongo'] = function($c) {
    $config = $c->get('settings')['mongo'];

    $dsn = sprintf('mongodb://%s:%s', $config['host'], $config['port']);

    if (!empty($config['username'])) {
        $dsn = sprintf('mongodb://%s:%s@%s:%s', $config['username'], $config['password'], $config['host'], $config['port']);
    }

    $client = new \MongoDB\Client($dsn);

    return $client;
};

// Predis
$container['redis'] = function($c) {
    $config = $c->get('settings')['redis'];

    $client = new \Predis\Client([
        'scheme'   => 'tcp',
        'host'     => $config['host'],
        'port'     => $config['port'],
        'password' => $config['password'],
        'database' => $config['database'],
    ], ['prefix' => $config['prefix']]);

    return $client;
};

// Flash
$container['flash'] = function() {
    return new \Slim\Flash\Messages();
};

// Monolog
$container['logger'] = function($c) {
    $config = $c->get('settings')['logger'];

    $logger = new \Monolog\Logger($config['name']);

    $handler = new \Monolog\Handler\StreamHandler($config['path'], $config['level']);
    $handler->setFormatter(new \Monolog\Formatter\LineFormatter(null, null, true, true));

    $logger->pushHandler($handler);

    return $logger;
};

// Captcha
$container['captcha'] = function($c) {
    $config = $c->get('settings')['captcha'];

    $builder = new \Gregwar\Captcha\CaptchaBuilder;
    $builder->build($config['width'], $config['height']);

    return $builder;
};

if (!env('APP_DEBUG', false)) {
    // 404NotFound
    $container['notFoundHandler'] = function($c) {
        return function($request, $response) use ($c) {
            if ($request->isXhr()) {
                return $response->withJson([
                    'success' => false,
                    'msg'     => 'page not found',
                ], 200);
            }

            return $c->view->render($response, 'error/error.twig', [
                'title' => 404,
                'msg'   => 'page not found',
            ]);
        };
    };

    // 405NotAllowed
    $container['notAllowedHandler'] = function($c) {
        return function($request, $response, $methods) use ($c) {
            if ($request->isXhr()) {
                return $response->withJson([
                    'success' => false,
                    'msg'     => 'method not allowed',
                ], 200);
            }

            return $c->view->render($response, 'error/error.twig', [
                'title' => 405,
                'msg'   => 'method not allowed',
            ]);
        };
    };

    // ErrorHandler
    $container['errorHandler'] = function($c) {
        return function($request, $response, $e) use ($c) {
            $c->logger->error(sprintf("%s in %s:%s\n%s", $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));

            if (env('ERROR_MAIL', false)) {
                \App\Helpers\MailerHelper::sendErrorMail($e);
            }

            if ($request->isXhr()) {
                return $response->withJson([
                    'success' => false,
                    'msg'     => 'server internal error',
                ], 200);
            }

            return $c->view->render($response, 'error/error.twig', [
                'title' => 500,
                'msg'   => 'server internal error',
            ]);
        };
    };

    // PHPErrorHandler
    $container['phpErrorHandler'] = function($c) {
        return function($request, $response, $e) use ($c) {
            $c->logger->error(sprintf("%s in %s:%s\n%s", $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));

            if (env('ERROR_MAIL', false)) {
                \App\Helpers\MailerHelper::sendErrorMail($e);
            }

            if ($request->isXhr()) {
                return $response->withJson([
                    'success' => false,
                    'msg'     => 'server internal error',
                ], 200);
            }

            return $c->view->render($response, 'error/error.twig', [
                'title' => 500,
                'msg'   => 'server internal error',
            ]);
        };
    };
}

// Providers
$providers = array_merge(
    require __DIR__ . '/../providers/dao.php',
    require __DIR__ . '/../providers/cache.php',
    require __DIR__ . '/../providers/service.php'
);

foreach ($providers as $alias => $obj) {
    $container[$alias] = function($c) use ($obj) {
        $instance = new $obj($c);

        return $instance;
    };
}
?>