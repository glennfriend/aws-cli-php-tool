<?php

Route::get('/', function () {
    return view('welcome');
});

Route::get('/instances',        'Home\InstanceController@show');
Route::get('/volumns',          'Home\VolumnController@show');
Route::get('/volumns/perform',  'Home\VolumnController@perform');
// Route::get('/snapshots', 'Home\HomeController@show');
// Route::get('/images', 'Home\HomeController@show');
// Route::get('/ips', 'Home\HomeController@show');
