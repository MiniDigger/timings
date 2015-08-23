<?php
use Starlis\Timings\Json\TimingHandler;
use Starlis\Timings\Json\TimingsMaster;
use Starlis\Timings\Template;

/**
 * @var TimingsMaster $timingsData
 */
$timingsData = TimingsMaster::getInstance();
$tpl = Template::getInstance();

global $totalTime;
$totalTime = 0;
$totalTimings = 0;
foreach ($timingsData->data as $data) {
	$totalTime += $data->totalTime;
	//var_dump($data->minuteReports);
	foreach ($data->handlers as $handler) {
		$totalTimings += $handler->count;
	}
}


define('LAG_ONLY', empty($_GET['all']));
//http://timings.aikar.co/dev/?id=2a72cf2099e0439780c91e64abadcf7d&start=1436841958&end=1436843422
$lag = $tpl->masterHandler->children;

printRecord($tpl->masterHandler);
usort($lag, 'lagSort');
printRows($lag, 1);

$cost = $timingsData->system->timingcost;
echo '<pre>';
echo "Timings cost: $cost - " . ($cost * $totalTimings) . " - Pct: "
	. round(((($cost * $totalTimings) / ($timingsData->sampletime * 1000000000 / 100))), 2) . "%\n\n";
echo '</pre>';

function printRecord($l, $depth = 0) {
	static $i;
	$tpl = Template::getInstance();
	$lagTicks = $tpl->masterHandler->lagCount;
	$ticks = $tpl->masterHandler->count;
	$totalTime = $tpl->masterHandler->total;
	$lagTotalTime = $tpl->masterHandler->lagTotal;

	$id = $l->id->id . "_" . $i++;
	$total = LAG_ONLY ? $l->lagTotal : $l->total;
	$count = LAG_ONLY ? $l->lagCount : $l->count;

	$avg = round(($total / $count) / 1000000, 4);
	$tickAvg = round($avg * ($count / (LAG_ONLY ? $lagTicks : $ticks)), 4);
	$tickAvg = lagView($tickAvg);

	$totalPct = round($total / (LAG_ONLY ? $lagTotalTime : $totalTime) * 100, 2);
	if ($l->id->name == "Full Server Tick") { // always 100%
		$totalPct = lagView($totalPct, 200, 200, 200, 200);
	} else {
		$totalPct = lagView($totalPct, 25, 15, 7, 3);
	}
	$avg = lagView($avg);
	$name = cleanName($l->id);
	$total = round($total / 1000000000, 3);

	$indents = "";
	for ($j = 1; $j <= $depth; $j++) {
		$num = $j % 10;
		$indents .= "<div class='indent depth{$num}'></div>";
	}

	echo "<div class='full-timing-row'>$indents<div id='$id' class='timing-row'>
		<a href='#$id'>#</a><span class='name'>$name</span> - count(<span class='count'>$count</span>) -
		total(<span class='totalPct'>$totalPct%</span> <span class='totalTime'>{$total}s</span>) -
		avg(<span class='avgMs'>{$avg}ms</span> - <span class='tickAvgMs'>{$tickAvg}ms</span>)
		</div></div>\n";
}


function cleanName($name) {
	static $replacements = [
		['/net\.minecraft\.server\.v[^\.]+\./', 'nms.'],
		['/org\.bukkit\.craftbukkit\.v[^\.]+\./', 'obc.'],
	];
	$orig = $name;
	foreach ($replacements as $pattern) {
		$name = preg_replace($pattern[0], $pattern[1], $name);
	}
	$name = preg_replace_callback('/Event: ([a-zA-Z0-9\.]+) /', 'condensePackage', $name);
	return "<span title='$orig'>$name</span>";
}
function condensePackage($v) {

	$name = explode('.', $v[1]);
	$last = array_pop($name);
	$name = array_map(function($v) { return $v[0]; }, $name);
	$name[] = $last;
	return 'Event: ' .implode('.', $name).' ';
}
$processMap = [];
function printRows($lag, $level) {
	global $processMap;
	$tpl = Template::getInstance();
	foreach ($lag as $l) {
		if ($l->lagTotal < 500000) {
//			continue;
		}

		printRecord($l, $level);
		$id = $l->id->id;
		$h = $tpl->handlerData[$id];

		if (!empty($h->children) && ++$processMap[$id] == 1) {
			$children = array_filter($h->children, 'lagFilter');
			if (!empty($children)) {
				$children = array_map(function($v) {
					$tpl = Template::getInstance();
					$h = $tpl->handlerData[$v->id->id];
					$v->children = $h->children;
					return $v;
				}, $children);
				usort($children, 'lagSort');
				printRows($children, $level + 1);
			}
			--$processMap[$id];
		}
	}
}

function lagFilter($e) {
	$e->avg = 0;
	$count = LAG_ONLY ? $e->lagCount : $e->count;
	$total = LAG_ONLY ? $e->lagTotal : $e->total;
	if ($count > 0) {
		$e->avg = ($total / $count) * $e->mergedCount;
	}

	return $total > 10 && $e->avg > 20000;
}

function lagSort($a, $b) {
	//return $a->avg > $b->avg ? -1 : 1;
	$total = LAG_ONLY ? 'lagTotal' : 'total';
	return $a->$total > $b->$total ? -1 : 1;
}

function lagView($tickAvg, $t1 = 25, $t2 = 15, $t3 = 5, $t4 = 1) {
	if ($tickAvg > $t1) {
		$tickAvg = "<span style='color:red'>$tickAvg</span>";
	} else if ($tickAvg > $t2) {
		$tickAvg = "<span style='color:orange'>$tickAvg</span>";
	} else if ($tickAvg > $t3) {
		$tickAvg = "<span style='color:yellow'>$tickAvg</span>";
	} else if ($tickAvg > $t4) {
		$tickAvg = "<span style='color:white'>$tickAvg</span>";
	}

	return $tickAvg;
}