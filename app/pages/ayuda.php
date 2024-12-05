<?php
if (isset($_GET['status']) && isset($_GET['message'])) {
    $status = htmlspecialchars($_GET['status']);
    $message = htmlspecialchars(urldecode($_GET['message']));

    echo "<div class='notification ".($status === 'success' ? 'success' : 'error')."'>";
    echo "<p>$message</p>";
    echo "</div>";
}
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
    </div>
  </section>

  <!-- Sección de Contacto -->
  <section>
    <h2 class="text-xl sm:text-2xl font-semibold mb-4 text-center sm:text-left">
      <?php echo translate('ayuda.contacto'); ?>
    </h2>
    <form id="contactForm" method="POST" action="/esc-backend/app/pages/ayudaForm.php"class="<?php echo $theme === 'dark' ? 'bg-gray-800 text-white' : 'bg-white text-black'; ?> shadow-md rounded-md p-4 sm:p-6 space-y-4">
      <div>
        <label for="name" class="block font-medium text-sm sm:text-base">
          <?php echo translate('ayuda.form.nombre'); ?>
        </label>
        <input type="text" id="name" name="name" required class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label for="email" class="block font-medium text-sm sm:text-base">
          <?php echo translate('ayuda.form.email'); ?>
        </label>
        <input type="email" id="email" name="email" required class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label for="message" class="block font-medium text-sm sm:text-base">
          <?php echo translate('ayuda.form.mensaje'); ?>
        </label>
        <textarea id="message" name="message" rows="4" required class="mt-1 w-full rounded-md border <?php echo $theme === 'dark' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-black'; ?> focus:ring-2 focus:ring-blue-500"></textarea>
      </div>
      <button type="submit" class="bg-blue-500 text-white rounded-md px-4 py-2 sm:px-6 sm:py-3 hover:bg-blue-600 w-full sm:w-auto">
        <?php echo translate('ayuda.form.enviar'); ?>
      </button>
    </form>
  </section>
</div>