<?php

namespace jtreminio\Silex;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Aura\Sql\ConnectionFactory;

class AuraSqlServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['db.default_options'] = array(
            'driver'   => 'mysql',
            'dbname'   => null,
            'host'     => 'localhost',
            'user'     => 'root',
            'password' => null,
            'port'     => null,
        );

        $app['dbs.options.initializer'] = $app->protect(function () use ($app) {
            static $initialized = false;

            if ($initialized) {
                return;
            }

            $initialized = true;

            if (!isset($app['dbs.options'])) {
                $app['dbs.options'] = array('default' => isset($app['db.options']) ? $app['db.options'] : array());
            }

            $tmp = $app['dbs.options'];
            foreach ($tmp as $name => &$options) {
                $options = array_replace($app['db.default_options'], $options);

                $options['driver'] = str_replace('pdo_', '', $options['driver']);

                if (!isset($app['dbs.default'])) {
                    $app['dbs.default'] = $name;
                }
            }
            $app['dbs.options'] = $tmp;
        });

        $app['dbs'] = $app->share(function ($app) {
            $app['dbs.options.initializer']();

            $dbs = new \Pimple();
            $factory = new ConnectionFactory;
            foreach ($app['dbs.options'] as $name => $options) {
                if ($app['dbs.default'] === $name) {
                    // we use shortcuts here in case the default has been overridden
                    $config = $app['db.config'];
                } else {
                    $config = $app['dbs.config'][$name];
                }

                $dbs[$name] = $dbs->share(function ($dbs) use ($options, $config, $factory) {
                    $dsn = 'host=' . $config['host'] . ';dbname=' . $config['dbname'];

                    return $factory->newInstance(
                        $config['driver'],
                        $dsn,
                        $config['user'],
                        $config['password']
                    );
                });
            }

            return $dbs;
        });

        $app['dbs.config'] = $app->share(function ($app) {
            $app['dbs.options.initializer']();

            $configs = new \Pimple();
            foreach ($app['dbs.options'] as $name => $options) {
                $configs[$name] = $options;
            }

            return $configs;
        });

        // shortcuts for the "first" DB
        $app['db'] = $app->share(function ($app) {
            $dbs = $app['dbs'];

            return $dbs[$app['dbs.default']];
        });

        $app['db.config'] = $app->share(function ($app) {
            $dbs = $app['dbs.config'];

            return $dbs[$app['dbs.default']];
        });
    }

    public function boot(Application $app)
    {
    }
}
