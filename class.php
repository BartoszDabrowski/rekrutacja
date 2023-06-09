﻿<?php

//klasa zbiorcza waluty
class Currency {
    private $curr;
    private $code;
    private $bid;
    private $ask;


    function __construct($curr, $code, $bid, $ask)
    {
        $this->curr = $curr;
        $this->code = $code;
        $this->bid = floatval($bid);
        $this->ask = floatval($ask);
    }

    //gettery
    function GetCurr(){
        return $this->curr;
    }
    function GetCode(){
        return $this->code;
    }
    function GetBid(){
        return $this->bid;
    }
    function GetAsk(){
        return $this->ask;
    }
    //metoda zwracająca wartości w celu dodania ich do bazy
    function to_database()
    {
        return "NULL,'$this->curr','$this->code','$this->bid','$this->ask','".date('Y-m-d')."'";
    }
    //metoda zwracająca obiekt o ustawionych polach; index 0 to własność id, która jest niepotrzebna,
    static function from_database($array)
    {
        return new Currency ($array[1], $array[2], $array[3], $array[4]);
    }

}
//klasa pomagająca dodać dane do bazy
class GetCurrencies{
   private static $url = 'https://api.nbp.pl/api/exchangerates/tables/C?format=json';
   static function get()
   {
    $response = file_get_contents(GetCurrencies::$url);
    $data = json_decode($response, true);
    if (empty($data)) return array();
    $currencies_to_return = [];

    $currencies = $data[0]['rates'];
    foreach ($currencies as $currency) {
        
        $actual_curr = new Currency($currency['currency'], $currency['code'], $currency['bid'], $currency['ask']);
        array_push($currencies_to_return,$actual_curr);
    } 
    return $currencies_to_return;
   }
}
//klasa abstrakcyjna, umożliwiająca wykonywanie zapytań do bazy
abstract class DatabaseCommand{
    protected $connection;
    protected $table;
    function __construct($connection, $table){
        $this->connection = $connection;
        $this->table = $table;
    }

    abstract function execute($data = NULL);
    
}

//klasa umożliwiająca dodanie rekordów
class InsertCommand extends DatabaseCommand{
    function execute($data = NULL){
        if(is_array($data))
        {
            $command = "INSERT INTO ".$this->table." VALUES";
            foreach($data as $query) $command .= " (".$query."),";

            $command = substr($command,0,-1);
        }
        else $command = "INSERT INTO ".$this->table." VALUES (".$data.")";
        
        $this->connection -> query($command);
        
        
    }
}
//klasa umożliwiająca odczytanie rekordów
class SelectCommand extends DatabaseCommand{
    function execute($data = NULL){
       if(is_null($data)) $command = "SELECT * FROM ".$this->table;
       else $command = "SELECT * FROM ".$this->table." WHERE ".$data;

        return $this->connection -> query($command)-> fetch_all();
    }
}
//klasa służąca operacjom walutowym
class Transaction{
    private $from;
    private $to;
    private $from_ammount;
    private $to_ammount;


    function __construct($from,$to, $from_ammount)
    {
        $this->from = $from;
        $this->to = $to;
        $this->from_ammount = floatval($from_ammount);
    }
    //fukncja obliczająca kwotę w nowej walucie
    function calc(){
        $this->to_ammount = round($this->from->GetBid()*$this->from_ammount/$this->to->GetAsk(),2);
        return $this->to_ammount;
    }
    //funkcja wysyłająca informacje o przewalutowaniu do bazy danych
    function save($connection){
        $send = new InsertCommand($connection, 'transactions');//utworzenie obiektu wstawiającego dane do bazy
        //przystosowanie wartości do wstawienia; przy tworzeniu obiektu klasy Transactions podaliśmy obiekty klasy Currency. Dlatego wywołanie metod tej klasy tutaj działa
        $command = [$this->from->GetCode() ,$this->to->GetCode() ,$this->from_ammount, $this->to_ammount, $this->from->GetBid()/$this->to->GetAsk()]; 
        $command = join("','",$command);
        $command = "'".$command."'";
        //poprawienie i rozdzielenie wartości w kwerendzie cudzysłowiami i przecinkami
        $send->execute($command);
        //wykonanie zapytania
    }

}

?>