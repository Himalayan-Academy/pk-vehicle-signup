<?php

use Psr\Log\LoggerInterface;
//use Exception;

final class ReservationStore
{
    private $logger;
    private $days;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;

        $this->logger->info("Opening datastore");
        ORM::configure('logging', true);
        ORM::configure('sqlite:'.  __DIR__ . '/data.sqlite');
        ORM::configure('id_column_overrides', array(
            'car' => 'car_id',
            'reservation' => 'reservation_id',
        ));

        // get the next seven days
        $context = [ 'http' => [ 'method' => 'GET' ], 'ssl' => [ 'verify_peer' => false, 'allow_self_signed'=> true ] ];
        $context = stream_context_create($context);
        $daysJson = file_get_contents("https://akasha.hindu.org:8080/days/14",false,$context);
        $this->days = json_decode($daysJson,true);
    }

    public function get_days() {
        return $this->days;
    }

    public function get_data_for_template() {
        $days = $this->get_days();
        $cars = $this->get_cars();
        $data = Array("days" => Array());

        foreach ($days as $day) {
            $item = array(
                "SunOrStar" => $day["SunOrStar"],
                "Date" => $day["Date_"],
                "SunOrStar_Num" => $day["SunOrStar_Num"],
                "Cars" => array()
            );

            foreach ($cars as $car) {
                $carItem = $car;
                $carItem["Reservations"] = $this->get_reservations_for_car($car, $day);

                array_push($item["Cars"], $carItem);
            }

            array_push($data["days"], $item);
        }

        return $data;

    }

    public function get_cars() {
        $cars = ORM::for_table('car')->where('is_available', 1)->find_array();
        $this->logger->info("SQL: " . ORM::get_last_query());
        return $cars;
    }

    public function how_many_cars() {
        $count = ORM::for_table('car')->count();
        return $count;
    }

    public function get_car($id) {
        $car = ORM::for_table('car')->find_one($id);
        return $car;
    }

    public function is_car_available($id) {
        $car = ORM::for_table('car')->find_one($id);

        if (!$car) {
            return false;
        }

        return $car->is_available;
    }

    public function get_reservations_for_car($car, $day) {
        $this->logger->info("Get Reservations:", $day);

        $reservations = ORM::for_table("reservation")
            ->where("car_id", $car["car_id"])
            ->where_like("start", substr($day["Date_"], 0, 10) . "%")
            ->order_by_asc("start")
            ->find_array();

        $this->logger->info("SQL: " . ORM::get_last_query());


        return $reservations;
    }

    public function make_reservation($id, $data) {
        // check if car is_available is 0, which means the car is somehow unavailable (repair shop, etc)

        $car = $this->get_car($id);

        if ($car->is_available == 0) {
            return array(
                "status" => false,
                "msg"=>"Car is not available"
            );
        }


        $date_created = date(DATE_RFC3339);
        $start = $data["date"] . " " . $data["start"];
        $end = $data["date"] . " " . $data["end"];
        $this->logger->info("START: {$start}");
        $this->logger->info("END: {$end}");

        $reservation = ORM::for_table('reservation')->create();

        $reservation->set('who', $data["who"]);
        $reservation->set('comment', $data["comment"]);
        $reservation->set('car_id', $id);
        $reservation->set('date_created', $date_created);
        try {
            $this->logger->info("Interval: {$start} | {$end}");

            $reservation->set('start', $start);
            $reservation->set('end', $end);


            $result = $reservation->save();
            $this->logger->info("SQL: " . ORM::get_last_query());
        } catch(Exception $e) {
            $this->logger->error("EXCEPTION: " . $e->getMessage());
            throw $e;
        }


        return array(
            "status" => $result
        );

    }

    public function update_reservation($id, $data) {
        // check if car is_available is 0, which means the car is somehow unavailable (repair shop, etc)

        $car = $this->get_car($data["car_id"]);

        if ($car->is_available == 0) {
            return array(
                "status" => false,
                "msg"=>"Car is not available"
            );
        }

        $date_created = date(DATE_RFC3339);
        $start = $data["date"] . " " . $data["start"];
        $end = $data["date"] . " " . $data["end"];
        $this->logger->info("START: {$start}");
        $this->logger->info("END: {$end}");

        $reservation = ORM::for_table('reservation')->find_one($id);

        $reservation->set('who', $data["who"]);
        $reservation->set('comment', $data["comment"]);
        $reservation->set('car_id', $data["car_id"]);
        $reservation->set('date_created', $date_created);
        try {
            $this->logger->info("Interval: {$start} | {$end}");

            $reservation->set('start', $start);
            $reservation->set('end', $end);


            $result = $reservation->save();
            $this->logger->info("SQL: " . ORM::get_last_query());
        } catch(Exception $e) {
            $this->logger->error("EXCEPTION: " . $e->getMessage());
            throw $e;
        }


        if (!$this->check_for_conflict($id)) {
            // conflict with the dates...
            $reservation->delete();
            return array(
                "status" => false,
                "msg"=>"Conflicting schedule for car"
            );
        }

        return array(
            "status" => $result
        );
    }

    public function delete_reservation($id) {
        $reservation = ORM::for_table('reservation')->find_one($id);

        try {
            $result = $reservation->delete();
            $this->logger->info("SQL: " . ORM::get_last_query());
        } catch(\Exception $e) {
            $this->logger->error("EXCEPTION: " . $e->getMessage());
            throw $e;
        }

        return array(
            "status" => $result
        );
    }

    public function check_for_conflict($reservation_id) {
        // check if there is a reservation for the given period...
        // step 1: query for all reservations on the same date range for the given car
        // step 2: if this results in a non-zero set, then we can't make the reservation (someone already reserved)

        /*
         * CUE (this works, and detects conflicts in the table after they happen):
          SELECT *,
               EXISTS (SELECT 1
                       FROM reservation AS other
                       WHERE other.reservation_id != reservation.reservation_id
                         AND reservation.car_id = other.car_id
                         AND other.end > reservation.start
                         AND other.start < reservation.end) AS conflict
        FROM reservation

        interval algebra: http://en.wikipedia.org/wiki/Allen%27s_Interval_Algebra
         */


        /*
         * Implementation below makes a reservation without checking for conflicts...
         * TODO: Check for conflicts (cue interval algebra above)
         */


        /*$conflicts = ORM::for_table('reservation')
            ->where("car_id",$car_id)
            ->where_gte("end", $start)
            ->where_lte("start",$end)
            ->count();

        $this->logger->info("Conflicts SQL: " . ORM::get_last_query());
        $this->logger->info("Conflicts: " . $conflicts);


        if ($conflicts > 0) {
            return false;
        }

        return true;*/

        $query = "SELECT *,
               EXISTS (SELECT 1
                       FROM reservation AS other
                       WHERE other.reservation_id != reservation.reservation_id
                         AND reservation.car_id = other.car_id
                         AND other.end > reservation.start
                         AND other.start < reservation.end) AS conflict
        FROM reservation
        WHERE reservation.reservation_id = $reservation_id
        ";

        $reservation = ORM::for_table('reservation')
            ->raw_query($query)->find_array();

        $this->logger->info("Conflicts SQL: " . ORM::get_last_query());
        $this->logger->info("Conflicts: " . $reservation);

        if ($reservation[0]["conflict"] == 1) {
            return false;
        } else {
            return true;
        }


    }


}
