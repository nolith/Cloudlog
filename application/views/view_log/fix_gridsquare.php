<h1>QSO without gridsquare</h1>
<div>QSO without WWL: <?php echo $results->num_rows(); ?></div>
<div><a href="./">Update a batch</a></div>
<h2>QSO list</h2>
<table>
	<tr>
	<th>Callsign</th>
	<th>SOTA</th>
	<th>SIG</th>
	<th>Comment</th>
	</tr>
<?php foreach ($results->result() as $row) { ?>
	<tr>
		<td><?php echo $row->COL_CALL; ?></td>
		<td><?php echo $row->COL_SOTA_REF; ?></td>
		<td><?php echo $row->COL_SIG . " - " . $row->COL_SIG_INFO; ?></td>
		<td><?php echo $row->COL_COMMENT; ?></td>
	</tr>
<?php } ?>
</table>

