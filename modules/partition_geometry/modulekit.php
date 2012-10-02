<?
$name="Separates a spatial table by its geometry column";

$depend=array("partition");

$include=array(
  'pgsql-functions'=>array(
    "functions.sql",
  ),
  'pgsql-init'=>array(
    "init.sql",
  ),
);
