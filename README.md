# BACKEND PHP NATIVO PARA LA APLICACIÓN DE ENERGÍA SOLAR CANARIAS

En desarrollo...
FRONTEND: https://app-energiasolarcanarias.com

## Características

En desarrollo...

## APIREST Y BACKEND

/tu-proyecto
│
├── /app
│   ├── /controllers    --> Controladores de la aplicación (UserController.php, ProductController.php)
│   ├── /models         --> Modelos que interactúan con la base de datos (User.php, Product.php)
│   ├── /services       --> Lógica de negocio o servicios (AuthService.php, ProductService.php)
│   ├── /middlewares    --> Middlewares que procesan solicitudes antes de los controladores (AuthMiddleware.php)
│   ├── /utils          --> Funciones o clases auxiliares y reutilizables (Config.php, Response.php)
│   └── /routers        --> Enrutadores que gestionan las rutas de la aplicación (routes.php)
│
├── /config             --> Archivos de configuración (database.php, config.php)
│
├── /public             --> Archivos accesibles desde el navegador (index.php, index.html)
│   ├── /assets         --> Recursos estáticos (CSS, JS, imágenes, fuentes)
│   │   ├── /css        --> Archivos de estilos CSS (styles.css)
│   │   ├── /js         --> Archivos JavaScript (scripts.js)
│   │   ├── /img        --> Imágenes (logo.png, banner.jpg)
│   │   └── /fonts      --> Fuentes personalizadas (custom-font.ttf)
│   └── /uploads        --> Archivos subidos por los usuarios (imagen_perfil.jpg, documentos.pdf)
│
├── /storage            --> Almacenamiento de datos temporales o logs (logs, archivos subidos temporalmente)
│
├── /vendor             --> Dependencias externas instaladas con Composer
│
├── .env                --> Variables de entorno (credenciales, configuraciones sensibles)
├── .htaccess           --> Configuración del servidor web para manejo de rutas y acceso
└── composer.json       --> Gestión de dependencias del proyecto (librerías externas)
