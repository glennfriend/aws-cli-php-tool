<?php

Route::get('/', function () {
    return view('welcome');
});

Route::get('/instances',         'Home\InstanceController@show');
Route::get('/volumes',           'Home\VolumeController@show');
Route::get('/volumes/perform',   'Home\VolumeController@perform');
Route::get('/snapshots',         'Home\SnapshotController@show');
Route::get('/snapshots/perform', 'Home\SnapshotController@perform');
// Route::get('/images',         'Home\ImageController@show');
// Route::get('/ips',            'Home\IpController@show');
