<section class="panel due-chart">
  <h2>Vencimientos en los próximos 30 días</h2>
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
</section>
