<?php

class ZohoClient{
    private $id;
    private $ultimo_login;
    private $clase_name;
    private $apellido;
    private $activo;
    private $email;
    private $Account_Name;
    private $imagen;
    private $movil;
    private $empresa;
    private $Poblaci_n;
    private $NIF;

    public function __construct($id = "", $email = "", $Account_Name = "", $imagen = "", $movil = "", $empresa = "", $Poblaci_n = "", $NIF = "", $ultimo_login = "", $clase_name = "", $apellido = "", $activo = "") {
        $this->id = $id;
        $this->email = $email;
        $this->Account_Name = $Account_Name;
        $this->imagen = $imagen;
        $this->movil = $movil;
        $this->empresa = $empresa;
        $this->Poblaci_n = $Poblaci_n;
        $this->NIF = $NIF;
        $this->activo = $activo;
        $this->apellido = $apellido;
        $this->clase_name = $clase_name;
        $this->ultimo_login = $ultimo_login;
    }
    // Getters y Setters
    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;
    }

    public function getAccountName() {
        return $this->Account_Name;
    }

    public function setAccountName($Account_Name) {
        $this->Account_Name = $Account_Name;
    }

    public function getImagen() {
        return $this->imagen;
    }

    public function setImagen($imagen) {
        $this->imagen = $imagen;
    }

    public function getMovil() {
        return $this->movil;
    }

    public function setMovil($movil) {
        $this->movil = $movil;
    }

    public function getempresa() {
        return $this->empresa;
    }

    public function setempresa($empresa) {
        $this->empresa = $empresa;
    }

    public function getPoblacion() {
        return $this->Poblaci_n;
    }

    public function setPoblacion($Poblaci_n) {
        $this->Poblaci_n = $Poblaci_n;
    }

    public function getNIF() {
        return $this->NIF;
    }

    public function setNIF($NIF) {
        $this->NIF = $NIF;
    }
    public function getActivo() {
        return $this->activo;
    }

    public function setActivo($activo) {
        $this->activo = $activo;
    }
    public function getClaseName() {
        return $this->clase_name;
    }

    public function setClaseName($clase_name) {
        $this->clase_name = $clase_name;
    }
    public function getApellido() {
        return $this->apellido;
    }

    public function setApellido($apellido) {
        $this->apellido = $apellido;
    }
    public function getUltimoLogin() {
        return $this->ultimo_login;
    }

    public function setUltimoLogin($ultimo_login) {
        $this->ultimo_login = $ultimo_login;
    }

    //Patron de diseño build pattern que se declara algo así
    /**
     * $cliente = (new ZohoClientBuilder())
     *->setId("456")
     *->setCorreoElectronico1("juan@example.com")
     *->setApellido("Pérez")
     *->setMovil("654321987")
     *->build();
     */
    public function build() {
        return new ZohoClient(
            $this->id, $this->email, $this->Account_Name, $this->imagen,
            $this->movil, $this->empresa, $this->Poblaci_n, $this->NIF,
            $this->ultimo_login, $this->clase_name, $this->apellido, $this->activo
        );
    }
}