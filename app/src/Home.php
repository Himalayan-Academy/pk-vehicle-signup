<?php
namespace App;

use Slim\Views\Twig;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

require_once "ReservationStore.php";
use ReservationStore;
use Exception;

final class Home
{
    private $view;
    private $logger;
    private $storage;

    public function __construct(Twig $view, LoggerInterface $logger)
    {
        $this->view = $view;
        $this->logger = $logger;
        $this->logger->info("Creating data store");
        try {
            $this->storage = new ReservationStore($logger);
        } catch (Exception  $e) {
            $this->logger->info("An error has occured loading the data store");
            $this->logger->info($e->getMessage());
        }
        $this->logger->info("Car count: " . $this->storage->how_many_cars());
        $cars = $this->storage->get_cars();
        $this->logger->info("Cars: " . $cars[0]["name"], $cars);

    }

    public function index(Request $request, Response $response, $args)
    {
        $this->logger->info("Home page action dispatched from home.php");

        $data = $this->storage->get_data_for_template();
        
        $this->view->render($response, 'home.twig', $data);
        return $response;
    }

    public function new_signup(Request $request, Response $response, $args)
    {
        $this->logger->info("New sign up");

        $parsedBody = $request->getParsedBody();

        $this->logger->info("JSON:", $parsedBody);

        $id = $parsedBody["id"];
        $data = $parsedBody["data"];
        $result = $this->storage->make_reservation($id, $data);

        $newResponse = $response->withJson($result,200);
        return $newResponse;
    }

    public function debug(Request $request, Response $response, $args)
    {
        $this->logger->info("Debug page action dispatched from home.php");

        $data = $this->storage->get_data_for_template();

        $newResponse = $response->withJson($data,200);
        return $newResponse;
    }
}
