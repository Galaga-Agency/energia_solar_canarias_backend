<?php
session_start();

// Cargar el archivo JSON con las traducciones
$jsonData = file_get_contents('./idiomas/idiomas.json');
$translations = json_decode($jsonData, true);

// Función para detectar el idioma preferido del navegador
function getBrowserLanguage() {
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        return $lang;
    }
    return 'es'; // Idioma por defecto si no se puede detectar
}

// Verificar si se ha seleccionado un idioma manualmente
if (isset($_GET['lang'])) {
    $language = $_GET['lang'];
    $_SESSION['lang'] = $language;
} elseif (isset($_SESSION['lang'])) {
    $language = $_SESSION['lang'];
} else {
    $language = getBrowserLanguage();
}

// Función para obtener la traducción
function translate($key) {
    global $translations, $language;
    $keys = explode('.', $key);
    $text = $translations[$language] ?? [];

    foreach ($keys as $k) {
        if (isset($text[$k])) {
            $text = $text[$k];
        } else {
            return $key; // Devolver la clave si no se encuentra la traducción
        }
    }
    return $text;
}

// Obtener la página seleccionada
$page = $_GET['page'] ?? 'inicio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menú Lateral Responsivo</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    /* Estilo para desplazar el contenido al abrir el menú */
    .content-shift {
        transform: translateX(256px); /* Ancho del menú lateral */
    }
    #content{
        width: 80%;
    }
  </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

  <!-- Botón de menú para móviles -->
  <div class="fixed top-4 left-4 z-50 md:hidden">
    <button id="menuButton" class="bg-blue-500 text-white p-2 rounded">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>

  <!-- Menú Lateral -->
  <div id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-white shadow-lg transition-transform duration-300 ease-in-out z-40 md:translate-x-0 transform -translate-x-full">
    <div class="p-4 bg-blue-500 text-white">
      <h2 class="text-lg font-semibold"><?php echo translate('menu.titulo') ?></h2>
    </div>
    <ul class="p-4 space-y-2">
      <li>
        <a href="?page=inicio" class="block px-4 py-2 text-gray-700 hover:bg-blue-100 rounded"><?php echo translate('menu.inicio') ?></a>
      </li>
      <li>
        <button onclick="toggleSubmenu('perfilSubmenu')" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-blue-100 rounded focus:outline-none">
          Usuarios
        </button>
        <ul id="perfilSubmenu" class="ml-4 mt-2 space-y-1 hidden">
          <li><a href="?page=get-usuarios" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 rounded"><?php echo translate('menu.GET') ?></a></li>
          <li><a href="?page=post-usuarios" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 rounded"><?php echo translate('menu.POST') ?></a></li>
          <li><a href="?page=put-usuarios" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 rounded"><?php echo translate('menu.PUT') ?></a></li>
          <li><a href="?page=delete-usuarios" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 rounded"><?php echo translate('menu.DELETE') ?></a></li>
        </ul>
      </li>
      <li>
        <button onclick="toggleSubmenu('configSubmenu')" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-blue-100 rounded focus:outline-none">
          Configuración
        </button>
        <ul id="configSubmenu" class="ml-4 mt-2 space-y-1 hidden">
          <li><a href="?page=bearer-token" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 rounded"><?php echo translate('menu.bearer_token') ?></a></li>
          <li><a href="?page=login" class="block px-4 py-2 text-gray-600 hover:bg-blue-50 rounded"><?php echo translate('menu.login') ?></a></li>
        </ul>
      </li>
      <li>
        <a href="?page=ayuda" class="block px-4 py-2 text-gray-700 hover:bg-blue-100 rounded"><?php echo translate('menu.ayuda') ?></a>
      </li>
      <li>
        <label href="?page=ayuda" class="block px-4 py-2 text-gray-700"><?php echo translate('menu.idiomas') ?></label>
      </li>
      <li>
      <div class="grid grid-cols-3 gap-4 h-24 place-items-center">
        <img id="img-es" src="public/assets/img/banderas/es.svg" width="40" class="rounded-full cursor-pointer" onclick="changeLanguage('es')">
        <img id="img-us" src="public/assets/img/banderas/us.svg" width="40" class="rounded-full cursor-pointer" onclick="changeLanguage('en')">
      </div>
      </li>
    </ul>
  </div>

  <!-- Contenido Principal -->
  <div id="content" class="p-8 transition-transform duration-300 ease-in-out">
    <?php
    // Mostrar contenido basado en la página seleccionada
    switch ($page) {
        case 'inicio':
            include('./app/pages/inicio.php');
            break;
        case 'get-usuarios':
            include('./app/pages/get-usuarios.php');
            break;
        case 'post-usuarios':
            include('./app/pages/post-usuarios.php');
            break;
        case 'put-usuarios':
            include('./app/pages/put-usuarios.php');
            break;
        case 'delete-usuarios':
            include('./app/pages/delete-usuarios.php');
            break;
        case 'bearer-token':
            include('./app/pages/bearer-token.php');
            break;
        case 'login':
            include('./app/pages/login.php');
            break;
        case 'ayuda':
            echo "<h1 class='text-2xl font-bold'>Ayuda</h1>";
            echo "<p>Contenido de Ayuda</p>";
            break;
        default:
            echo "<h1 class='text-2xl font-bold'>Página no encontrada</h1>";
            echo "<p>La página solicitada no existe.</p>";
            break;
    }
    ?>
  </div>

  <!-- JavaScript para manejar las subrutas y el menú responsivo -->
  <script>
   function changeLanguage(lang) {
    // Actualizar el parámetro 'lang' en la URL sin recargar la página
    const url = new URL(window.location);
    url.searchParams.set('lang', lang);
    window.history.pushState({}, '', url);

    // Realizar una solicitud para obtener las traducciones
    fetch('./idiomas/idiomas.json')
      .then(response => response.json())
      .then(data => {
        // Actualizar las traducciones en la página
        document.querySelectorAll('[data-translate]').forEach(element => {
          const key = element.getAttribute('data-translate');
          if (data[lang] && data[lang][key]) {
            element.textContent = data[lang][key];
          }
        });
      })
      .catch(error => console.error('Error al cargar las traducciones:', error));
  }

     // Función que se ejecuta al hacer clic en una imagen
  function handleImageClick(event) {
    const clickedImageId = event.target.id;
    let variable;

    switch (clickedImageId) {
      case 'img-es':
        variable = 'es';
        location.reload();
        break;
      case 'img-us':
        variable = 'us';
        location.reload();
        break;
      // Agrega más casos según sea necesario
      default:
        variable = 'default';
    }

    console.log('Imagen seleccionada:', variable);
    // Aquí puedes realizar la acción que desees con la variable
  }

  // Selecciona todas las imágenes y agrega el evento de clic
  const images = document.querySelectorAll('.grid img');
  images.forEach(image => {
    image.addEventListener('click', handleImageClick);
  });
  function toggleSubmenu(id) {
    const submenu = document.getElementById(id);
    submenu.classList.toggle('hidden');
  }

  // Función para abrir y cerrar el menú en dispositivos móviles
  const menuButton = document.getElementById('menuButton');
  const sidebar = document.getElementById('sidebar');
  const content = document.getElementById('content');

  menuButton.addEventListener('click', () => {
    if (window.innerWidth < 768) { // Solo ejecutar en dispositivos móviles
      sidebar.classList.toggle('-translate-x-full');
      content.classList.toggle('content-shift'); // Desplazar el contenido
    }
  });

  // Función para ajustar el desplazamiento del contenido basado en el tamaño de la ventana
  function adjustContentShift() {
    if (window.innerWidth > 768) {
      content.classList.add('content-shift'); // Desplazar el contenido si es más grande
    } else {
      content.classList.remove('content-shift'); // Remover el desplazamiento si es más pequeño
    }
  }

  // Llamar a la función inicialmente
  adjustContentShift();

  // Agregar evento resize para ajustar el contenido cuando se cambia el tamaño de la ventana
  window.addEventListener('resize', adjustContentShift);
</script>

</body>
</html>