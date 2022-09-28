# uuu9-signature
#API SDK & Middleware for Lumen

##Installation

`bootstrap.php`

add:

```
$app->register(Uuu9\Signature\SignServiceProvider::class);
```

copy config file `api_sign.php` to system config path

edit `api_sign.php`


`.ent.tp` add:

```
## 接口地址配置[/config/service_api/endpoint]
ENDPOINT_API=null
ENDPOINT_GATEWAY=null
```

Done!

##Attention Please !
