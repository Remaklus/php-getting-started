<?php

require('../vendor/autoload.php');

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();

$dbopts = parse_url(getenv('DATABASE_URL'));
$app->register(new Herrera\Pdo\PdoServiceProvider(),
  array(
    'pdo.dsn' => 'pgsql:dbname='.ltrim($dbopts["path"],'/').';host='.$dbopts["host"] . ';port=' . $dbopts["port"],
    'pdo.username' => $dbopts["user"],
    'pdo.password' => $dbopts["pass"]
  )
);

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr',
));

// Register view rendering
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/views',
));

// Our web handlers

$app->get('/', function() use($app) {
  $app['monolog']->addDebug('logging output.');
  return $app['twig']->render('index.twig');
});

// pizza server code
$app->get('/pizzas', function() use ($app){
  $query = $app['pdo']->prepare('SELECT id, name, description FROM pizzas');

  $query->execute();

  $pizzas = array();
  while($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $pizzas[] = $row;
  }

  return new Response(json_encode($pizzas), 200);
});

$app->post('/pizzas', function(Request $request) use ($app) {
  $data = $request->request->get('pizza');

  if($data == null)
  {
    return new Response('Invalid POST data.', 400);
  }

  $name = $data['name'];
  $desc = $data['description'];

  $query = $app['pdo']->prepare("INSERT INTO pizzas (name, description) VALUES (:name, :desc)");

  $bind[':name'] = $name;
  $bind[':desc'] = $desc;

  $result = $query->execute($bind);
  if($result)
  {
    return new Response('Success', 200);
  }
  return new Response('There was a problem with making your pizza.', 500);
});

$app->get('/toppings', function() use ($app) {
  $query = $app['pdo']->prepare('SELECT id, name FROM toppings');

  $query->execute();

  $toppings = array();
  while($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $toppings[] = $row;
  }

  return new Response(json_encode($toppings), 200);
});

$app->post('/toppings', function(Request $request) use ($app) {
  $data = $request->request->get('topping');

  if($data == null)
  {
    return new Response('Invalid POST data.', 400);
  }

  $name = $data['name'];

  $query = $app['pdo']->prepare("INSERT INTO toppings (name) values (:name)");
  $bind[':name'] = $name;
  $result = $query->execute($bind);

  if(strcmp($query->errorInfo()[0],"23505") == 0)
  {
    return new Response("The '$name' topping already exists", 400);
  }
  elseif($result)
  {
    return new Response('Success', 200);
  }
  return new Response('There was a problem with creating your toppings.', 500);
});

$app->get('/pizzas/{pizza_id}/toppings', function($pizza_id) use ($app) {
  $query = $app['pdo']->prepare("SELECT id, name FROM toppings JOIN pizza_toppings ON id = tid WHERE pid = :pid");
  $bind[':pid'] = $pizza_id;
  $query->execute($bind);

  $toppings = array();
  while($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $toppings[] = $row;
  }

  return new Response(json_encode($toppings), 200);
});

$app->post('/pizzas/{pizza_id}/toppings', function (Request $request, $pizza_id) use ($app) {
  $topping_id = $request->request->get('topping_id');

  if($topping_id == null)
  {
    return new Response('Invalid POST data.', 400);
  }

  $query = $app['pdo']->prepare("INSERT INTO pizza_toppings (tid, pid) values (:tid, :pid)");
  $bind[':pid'] = $pizza_id;
  $bind[':tid'] = $topping_id;
  $result = $query->execute($bind);

  if ($result)
  {
    return new Response('Success', 200);
  }
  return new Response('There was a problem with creating your topping.', 500);
});

$app->run();
