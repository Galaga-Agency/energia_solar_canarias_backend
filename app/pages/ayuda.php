<?php
// Aviso tras enviar el formulario. $_GET viene del propio redirect de ayudaForm.php,
// pero se trata como no fiable igualmente: el status se limita a un valor conocido y
// el mensaje se escapa (ENT_QUOTES) antes de imprimirlo, asi que no hay XSS reflejado
// aunque alguien manipule la URL.
if (isset($_GET['status'], $_GET['message'])) {
  $status = $_GET['status'] === 'success' ? 'success' : 'error';
  $message = htmlspecialchars(urldecode((string) $_GET['message']), ENT_QUOTES, 'UTF-8');

  // Clases dinámicas para éxito y error
  $classes = $status === 'success'
    ? 'bg-green-100 text-green-700 border-green-300'
    : 'bg-red-100 text-red-700 border-red-300';

  echo "<div class='notification border-l-4 p-4 rounded-md mb-4 $classes'>";
  echo "<p class='text-sm text-center'>$message</p>";
  echo "</div>";
}
/** @var string $theme Tema activo ('dark'|'light'); lo define index.php, que incluye esta pagina. */
?>
<div class="container mx-auto p-4 sm:p-6 lg:p-8">
  <!-- Título -->
  <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold mb-6 text-center">
    <?php echo translate('ayuda.titulo'); ?>
  </h1>

  <!-- Sección de Preguntas Frecuentes -->
  <section class="mb-8">
    <h2 class="text-xl sm:text-2xl font-semibold mb-4 text-center sm:text-left">
      <?php echo translate('ayuda.faq'); ?>
    </h2>
    <div class="space-y-4">
      <details class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4">
        <summary class="font-medium cursor-pointer text-lg">
          <?php echo translate('ayuda.faq1.titulo'); ?>
        </summary>
        <p class="mt-2 text-sm sm:text-base">
          <?php echo translate('ayuda.faq1.contenido'); ?>
        </p>
      </details>
      <details class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4">
        <summary class="font-medium cursor-pointer text-lg">
          <?php echo translate('ayuda.faq2.titulo'); ?>
        </summary>
        <p class="mt-2 text-sm sm:text-base">
          <?php echo translate('ayuda.faq2.contenido'); ?>
        </p>
      </details>
      <details class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4">
        <summary class="font-medium cursor-pointer text-lg">
          <?php echo translate('ayuda.faq3.titulo'); ?>
        </summary>
        <p class="mt-2 text-sm sm:text-base">
          <?php echo translate('ayuda.faq3.contenido'); ?>
        </p>
      </details>
      <details class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4">
        <summary class="font-medium cursor-pointer text-lg">
          <?php echo translate('ayuda.faq4.titulo'); ?>
        </summary>
        <p class="mt-2 text-sm sm:text-base">
          <?php echo translate('ayuda.faq4.contenido'); ?>
        </p>
      </details>
      <details class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4">
        <summary class="font-medium cursor-pointer text-lg">
          <?php echo translate('ayuda.faq5.titulo'); ?>
        </summary>
        <p class="mt-2 text-sm sm:text-base">
          <?php echo translate('ayuda.faq5.contenido'); ?>
        </p>
      </details>
      <details class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4">
        <summary class="font-medium cursor-pointer text-lg">
          <?php echo translate('ayuda.faq6.titulo'); ?>
        </summary>
        <p class="mt-2 text-sm sm:text-base">
          <?php echo translate('ayuda.faq6.contenido'); ?>
        </p>
      </details>
    </div>
  </section>

  <!-- Sección de Contacto -->
  <section>
    <h2 class="text-xl sm:text-2xl font-semibold mb-4 text-center sm:text-left">
      <?php echo translate('ayuda.contacto'); ?>
    </h2>
    <form id="contactForm" method="POST" action="index.php?page=ayudaForm" class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4 sm:p-6 space-y-4">
      <div>
        <label for="name" class="block font-medium text-sm sm:text-base">
          <?php echo translate('ayuda.form.nombre'); ?>
        </label>
        <input type="text" id="name" name="name" required maxlength="100" class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label for="email" class="block font-medium text-sm sm:text-base">
          <?php echo translate('ayuda.form.email'); ?>
        </label>
        <input type="email" id="email" name="email" required maxlength="254" class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label for="message" class="block font-medium text-sm sm:text-base">
          <?php echo translate('ayuda.form.mensaje'); ?>
        </label>
        <textarea id="message" name="message" rows="4" required maxlength="2000" class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500"></textarea>
      </div>
      <div>
        <label for="captcha" class="block font-medium text-sm sm:text-base">Escribe el texto del CAPTCHA:</label>
        <img src="./app/utils/captcha.php" alt="CAPTCHA" class="mb-2">
        <input type="text" id="captcha" name="captcha" required class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500">
      </div>
      <button type="submit" class="bg-blue-500 text-white rounded-md px-4 py-2 sm:px-6 sm:py-3 hover:bg-blue-600 w-full sm:w-auto">
        <?php echo translate('ayuda.form.enviar'); ?>
      </button>
    </form>
  </section>
</div>