<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo translate('ayuda.titulo'); ?></title>
</head>
<body class="<?php echo $theme === 'dark' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-black'; ?>">
  <div class="container mx-auto p-6">
    <!-- Título -->
    <h1 class="text-3xl font-bold mb-4 text-center">
      <?php echo translate('ayuda.titulo'); ?>
    </h1>

    <!-- Sección de Preguntas Frecuentes -->
    <section class="mb-8">
      <h2 class="text-2xl font-semibold mb-2">
        <?php echo translate('ayuda.faq'); ?>
      </h2>
      <div class="space-y-4">
        <details class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4">
          <summary class="font-medium cursor-pointer">
            <?php echo translate('ayuda.faq1.titulo'); ?>
          </summary>
          <p class="mt-2">
            <?php echo translate('ayuda.faq1.contenido'); ?>
          </p>
        </details>
        <details class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4">
          <summary class="font-medium cursor-pointer">
            <?php echo translate('ayuda.faq2.titulo'); ?>
          </summary>
          <p class="mt-2">
            <?php echo translate('ayuda.faq2.contenido'); ?>
          </p>
        </details>
        <details class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4">
          <summary class="font-medium cursor-pointer">
            <?php echo translate('ayuda.faq3.titulo'); ?>
          </summary>
          <p class="mt-2">
            <?php echo translate('ayuda.faq3.contenido'); ?>
          </p>
        </details>
      </div>
    </section>

    <!-- Sección de Contacto -->
    <section>
      <h2 class="text-2xl font-semibold mb-2">
        <?php echo translate('ayuda.contacto'); echo " (don't work for the moment)" ?>
      </h2>
      <form class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-6 space-y-4">
        <div>
          <label for="name" class="block font-medium">
            <?php echo translate('ayuda.form.nombre'); ?>
          </label>
          <input type="text" id="name" name="name" class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label for="email" class="block font-medium">
            <?php echo translate('ayuda.form.email'); ?>
          </label>
          <input type="email" id="email" name="email" class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label for="message" class="block font-medium">
            <?php echo translate('ayuda.form.mensaje'); ?>
          </label>
          <textarea id="message" name="message" rows="4" class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500"></textarea>
        </div>
        <button type="submit" class="bg-blue-500 text-white rounded-md px-6 py-2 hover:bg-blue-600">
          <?php echo translate('ayuda.form.enviar'); ?>
        </button>
      </form>
    </section>
  </div>
</body>
</html>
