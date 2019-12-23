<?php

Route::get('/', function () {
    return view('welcome');
});

Route::get('/instances', 'Home\HomeController@instances');
// Route::get('/volumns', 'Home\HomeController@');
// Route::get('/snapshots', 'Home\HomeController@');
// Route::get('/images', 'Home\HomeController@');
// Route::get('/ips', 'Home\HomeController@');
