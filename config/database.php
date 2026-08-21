<?php
    use Illuminate\Support\Str;
    return [
      'default'=>env('DB_CONNECTION','pgsql'),
      'connections'=>[
        'pgsql'=>['driver'=>'pgsql','url'=>env('DB_URL'),'host'=>env('DB_HOST','postgres'),'port'=>env('DB_PORT','5432'),'database'=>env('DB_DATABASE','jaringanku'),'username'=>env('DB_USERNAME','jaringanku'),'password'=>(is_readable(env('DB_PASSWORD_FILE', '/run/secrets/db_password')) ? trim(file_get_contents(env('DB_PASSWORD_FILE', '/run/secrets/db_password'))) : env('DB_PASSWORD', '')),'charset'=>'utf8','prefix'=>'','prefix_indexes'=>true,'search_path'=>'public','sslmode'=>env('DB_SSLMODE','prefer')]
      ],
      'migrations'=>['table'=>'migrations','update_date_on_publish'=>true],
      'redis'=>['client'=>env('REDIS_CLIENT','predis'),'options'=>['cluster'=>env('REDIS_CLUSTER','redis'),'prefix'=>env('REDIS_PREFIX',Str::slug(env('APP_NAME','jaringanku')).'-database-')], 'default'=>['url'=>env('REDIS_URL'),'host'=>env('REDIS_HOST','redis'),'password'=>env('REDIS_PASSWORD'),'port'=>env('REDIS_PORT','6379'),'database'=>env('REDIS_DB','0')], 'cache'=>['url'=>env('REDIS_URL'),'host'=>env('REDIS_HOST','redis'),'password'=>env('REDIS_PASSWORD'),'port'=>env('REDIS_PORT','6379'),'database'=>env('REDIS_CACHE_DB','1')]]
    ];
