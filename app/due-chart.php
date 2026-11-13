<section class="panel due-chart">
  <h2>Vencimientos en los próximos 30 días</h2>
  <?php if (!array_sum($dueBuckets)): ?>
    <p class="chart-empty muted">No hay solicitudes activas con vencimiento en este período.</p>
  <?php else: ?>
  <div class="due-bars">
    <?php $maxDue=max(1,...$dueBuckets); ?>
    <?php foreach ($dueBuckets as $week=>$count): ?>
      <?php $date=$chartStart->modify('+'.($week*7).' days')->format('d/m'); ?>
      <div>
        <span><?=$count?></span>
        <meter min="0" max="<?=$maxDue?>" value="<?=$count?>" aria-label="Solicitudes desde el <?=$date?>"><?=$count?></meter>
        <small><?=$date?></small>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
