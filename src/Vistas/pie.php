</main>
<footer class="pie">
  <?php /* Contacto y Quiénes somos: stickers chicos, como los del menú (con cuenta no están en la barra) */ ?>
  <nav class="pie__enlaces" aria-label="Pie de página">
    <?php foreach ([['contacto.php', 'Contacto', 'menu/contacto.svg'], ['nosotros.php', 'Quiénes somos', 'menu/nosotros.svg']] as [$ruta, $texto, $dibujo]) : ?>
      <a class="pie__enlace" href="<?= e(url($ruta)) ?>"<?= isset($marcar) ? $marcar($ruta) : '' ?>>
        <img class="pie__dibujo" src="<?= e(url('img/' . $dibujo)) ?>" alt="" width="34" height="34"><span><?= e($texto) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <p>Cooki · <?= date('Y') ?> · Proyecto integrador · Tecnicatura Universitaria en Programación · UTN Facultad Regional Haedo</p>
</footer>
<?php /* La manija de la heladera: también es la barra para desplazarse (manija.js). Con el mouse se arrastra;
         en el celular, se mantiene apretada un momento y después se arrastra. */ ?>
<div class="manija" aria-hidden="true"><div class="manija__agarre"></div></div>
</body>
</html>
