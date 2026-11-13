<section class="panel status-chart">
  <h2>Estado de las solicitudes</h2>
  <?php $chartTotal=array_sum(array_column($chartRows,'total')); ?>
  <?php if (!$chartTotal): ?>
    <p class="chart-empty muted">Los estados aparecerán cuando haya solicitudes.</p>
  <?php else: ?>
    <ul>
      <?php foreach (['active'=>'Activas','accepted'=>'Aceptadas','cancelled'=>'Canceladas'] as $chartKey=>$chartLabel): ?>
        <?php $chartValue=$chartKey==='cancelled'
            ? $chartTotal-array_sum(array_column($chartRows,'active'))-array_sum(array_column($chartRows,'accepted'))
            : array_sum(array_column($chartRows,$chartKey)); ?>
        <li><span><?=h($chartLabel)?></span><strong><?=$chartValue?></strong>
          <progress max="<?=$chartTotal?>" value="<?=$chartValue?>" aria-label="<?=h($chartLabel)?>"></progress>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
