# StompPhpStompBundle

This bundle provides [stomp-php](https://github.com/stomp-php/stomp-php) integration for symfony (3.4, 4.0).


## Quick Setup

```bash
composer require stomp-php/stomp-bundle 
```

- For Symfony 3.4 you need to register the bundle by adding `new StompPhp\StompBundle\StompPhpStompBundle()` to your `AppKernel.php`.
- For Symfony 4.0 you need to register the command by adding `StompPhp\StompBundle\Command\ConsumerCommand:` to your `services.yaml`. 

Create a service that is `callable`.

```php
class YourService {
  // is called for every message that is received from the queue
  public function __invoke(Stomp\Transport\Frame $message) {
    if ($mesage->body === '...') {
      return true;
    }
    return false;
  }

}
``` 

Define stomp clients and consumers (subscriptions).

```yaml
stomp_php_stomp:
  clients:
     default:
        broker_uri: 'tcp://localhost:61614'
        # default read timeout is one minute, which makes interactive stop requests very slow.
        read_timeout_ms: 750
        
  consumers:
     welcome:
        client: 'default'
        queue: '/welcome'
        service: AppBundle\YourService
```

Start to consume messages.

```bash
bin/console stomp:consumer welcome

```

## Configuration Options

Please not that client instances are not shared and they are private by default.

Consumers are always public as they are part of the dynamic console command that this bundle offers.

```yaml
stomp_php_stomp:
  clients:
     default:
        # use a broker_uri as connection string
        # failover://(tcp://localhost:61614,ssl://localhost:61612)?randomize=true
        # failover://(tcp://localhost:61614,ssl://localhost:61612)
        broker_uri: 'tcp://localhost:61614'

        # expose this client as service (stomp.clients.default)
        public: true

     example_broker:
        host: '127.0.0.1'
        port: 61612
        vhost: '/someVhost'

        # define heartbeats, please be aware that your processing 
        # needs to be faster than the heartbeat that you defined here.
        heartbeat_client_ms: 250
        heartbeat_server_ms: 250

        read_timeout_ms: 750
        write_timeout: 3

     simple_broker:
        broker_uri: 'failover://(tcp://localhost:61614,ssl://localhost:61612)?randomize=true'

        # define username and password
        user: 'username'
        password: 'password'

  consumers:
     welcome:
        # set the client to use
        client: 'default'

        # define what queue to subscribe to
        queue: '/welcome'

        # set the service (callable)
        service: AppBundle\Service\WelcomeService

     welcome_with_method:
        client: 'example_broker'
        queue: '/push/message'
        service: AppBundle\Service\MessageService

        # set the service method (if service itself is not a callable)
        service_method: 'onPushMessage'

        # define a message selector
        selector: "switch = 'green'"
```