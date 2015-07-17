<?php

$css = (object) [];

$css->globalBox = implode(' ', [
    'min-width: 400px;',
    'max-width: 600px;',
    'border: 1px solid #dddddd;',
    'border-radius: 3px;',
]);

$css->fontFamily = 'font-family: Arial, sans-serif;';
$css->fontSize = 'font-size: 13px;';
$css->color = 'color: #222222;';

$css->p = implode(' ', [
  $css->fontFamily,
  $css->fontSize,
  $css->color,
]);

$css->a = implode(' ', [
  $css->fontFamily,
  $css->fontSize,
  'color: #337ab7;',
]);
