<?php

class ZohoClient{
    private $id;
    private $Correo_electr_nico_1;
    private $Account_Name;
    private $Record_Image;
    private $M_vil;
    private $Empresa;
    private $Poblaci_n;
    private $NIF;

    public function __construct($id = "", $Correo_electr_nico_1 = "", $Account_Name = "", $Record_Image = "", $M_vil = "", $Empresa = "", $Poblaci_n = "", $NIF = "") {
        $this->id = $id;
        $this->Correo_electr_nico_1 = $Correo_electr_nico_1;
        $this->Account_Name = $Account_Name;
        $this->Record_Image = $Record_Image;
        $this->M_vil = $M_vil;
        $this->Empresa = $Empresa;
        $this->Poblaci_n = $Poblaci_n;
        $this->NIF = $NIF;
    }
    // Getters y Setters
    public function getId() {
        return $this->id;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function getCorreoElectronico1() {
        return $this->Correo_electr_nico_1;
    }

    public function setCorreoElectronico1($Correo_electr_nico_1) {
        $this->Correo_electr_nico_1 = $Correo_electr_nico_1;
    }

    public function getAccountName() {
        return $this->Account_Name;
    }

    public function setAccountName($Account_Name) {
        $this->Account_Name = $Account_Name;
    }

    public function getRecordImage() {
        return $this->Record_Image;
    }

    public function setRecordImage($Record_Image) {
        $this->Record_Image = $Record_Image;
    }

    public function getMovil() {
        return $this->M_vil;
    }

    public function setMovil($M_vil) {
        $this->M_vil = $M_vil;
    }

    public function getEmpresa() {
        return $this->Empresa;
    }

    public function setEmpresa($Empresa) {
        $this->Empresa = $Empresa;
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
}