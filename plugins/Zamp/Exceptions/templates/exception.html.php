<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title><?php echo $errorInfo['text'] ?>: <?php echo htmlspecialchars($errorInfo['name']); ?></title>
	<style type="text/css">
		body {
			margin: 0;
			padding: 20px;
			margin-top: 20px;
			background-color: #EFF1F4
		}

		body, td, th {
			font: 11px Verdana, Arial, sans-serif;
			color: #333
		}

		a {
			color: #333
		}

		h1 {
			margin: 0 0 0 10px;
			padding: 10px 0 10px 0;
			font-weight: bold;
			font-size: 120%
		}

		h2 {
			margin: 0;
			padding: 5px 0;
			font-size: 110%
		}

		ul {
			padding-left: 20px;
			list-style: decimal
		}

		ul li {
			padding-bottom: 5px;
			margin: 0
		}

		ol {
			font-family: monospace;
			white-space: pre;
			list-style-position: inside;
			margin: 0;
			padding: 10px 0
		}

		ol li {
			margin: -5px;
			padding: 0
		}

		ol .selected {
			font-weight: bold;
			background-color: #ddd;
			padding: 2px 0
		}

		table.vars {
			padding: 0;
			margin: 0;
			border: 1px solid #999;
			background-color: #fff;
		}

		table.vars th {
			padding: 2px;
			background-color: #ddd;
			font-weight: bold
		}

		table.vars td {
			padding: 2px;
			font-family: monospace;
			white-space: pre
		}

		p.error {
			padding: 10px;
			background-color: #f00;
			font-weight: bold;
			text-align: center;
			-moz-border-radius: 10px;
			-webkit-border-radius: 10px;
			border-radius: 10px;
		}

		p.error a {
			color: #fff
		}

		#main {
			padding: 30px 40px;
			border: 1px solid #ddd;
			background-color: #fff;
			text-align: left;
			-moz-border-radius: 10px;
			-webkit-border-radius: 10px;
			border-radius: 10px;
			min-width: 770px;
			max-width: 1024px
		}

		#message {
			padding: 10px;
			margin-bottom: 10px;
			background-color: #eee;
			-moz-border-radius: 10px;
			-webkit-border-radius: 10px;
			border-radius: 10px;
		}

		a.file_link {
			text-decoration: none;
		}

		a.file_link:hover {
			text-decoration: underline;
		}

		.code {
			overflow-x: auto;
		}
	</style>
	<script type="text/javascript">
		function toggle(id) {
			el = document.getElementById(id);
			el.style.display = el.style.display == 'none' ?'block' :'none';
		}
	</script>
</head>
<body>
<center>
	<div id="main">
		<div style="float: right">
			<img
				src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABwAAAAZCAYAAAAiwE4nAAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAAsTAAALEwEAmpwYAAAEfklEQVRIx7VUa0wUVxT+Znd2FxZk0YKACAtaGwEDUhUTBTEIItmKYk3UNqalD7StMSQ1JKatP5omTYyx0VRrjPERX7XWAG2t9GVi3drU2h+gi4BCWV67lOe6O/uYmXtPf0BRrMBK6UlObmbON9935p6HQEQI1o7uXeSy1dsjHn2Xlpr0oKzililoEiIKymvOr9q+pzyZZN894moHcbWDZN892lOeTN9fKHgrWB5NsInZ7joOrtv4JgR2F4r0AxTpRwisEes2bsNtW+eBYHmCEqw8kVsp6oy6jMUFYIoTxFUQqWBqNzIWr4aoC9NVnlxZNSWC1mqLsa6ubd36zbug+m3gXBlypoCYAuavx4Ytu1Fbay+2VluME/GJEwHsnT3WpLlzhbi4Z6D46gBosP/gVQDA669kIzJSRWxcApLnPie0dw3cALBw0k1z5dyKrIqyWHL1/Eye7n3kcX5MH75fRAAIAJUUZ5Cnez9JPYfI1XuDKsriqOZcbtakm6alte/yqsIi6LVt4KobxAIAqSPxwUEJxAPgqgcG0YH8NS+gxT5wZVI1/PrU0q1O54OoFfmvQZZsIBYA5zIy0maOYFZmJ4GYAuIyZG8jcvLfgMPhmnHlbG7pUws2NfUeWVvyMpj3d3DVB84C4MyPxNkP+8I0TQRn/qGY6gP316J4w6uob3AceirBzw9nnBD1RmN65nLIUhOIBUBcBjEZ5viQEZx5thFcdQ+50o+A5w7SM5dBFHWhFz5bdOpJ3MLjq63mdHrIr7f6PaXbPtBGht4DUwYAQXikyVTkb/gKtbYBNFpzYYoY3egarR6D7jCcPmtly5ZEh6/ZWucfdyycPep3ycmJ2phoAzx9ziERLoMzN4hJAICI8KEkp4VxcCaP+p4zGdHTw2FOiNB2OTzfAMgf80qrjmem1zf256zf9B6kvmvgqgeqrw2qvx1cGQRxBcQV5GRFIGepaeT5cfdJXbAUPY+79z15l47MWzDmH7a3P/g2Ly9X4O6LkKUWEPeOMbwMpnANiClPDkOBXteL3OXxQnNL72UA5n/V8NLR9Bdrb/ddLN+5VvD23wTA8d9MgNH0LD759DrS5oeUbN7RWjXqSu//OXi8sCBFkN11IFJAxMZ0e4cP12+6xsUQqZC9nShclYTWtsDJUTU8cyDlsE7URqTMC4Eiu8fN+/JVF7I3NuGlna2wlDaPi1VkN1LnR0GvF00n95kPAICm+tgcQ9N9V5ll9Tz4JSem2vySE5bCFDS3+t+uPjbHIA64dF/MioU2aoYGXndgQgJLngnWL0PR1iUje0n4hHimBhA1XYA5IVz8q1eu0oSGqCc6HV4ihAIQgso6MV4flNhDUR/iYqbBI1GqZtM7zVUzZ4p3rl5rQIgxesqvVCsa0O8y4Lc/nGp8rLhcBIA7Df7C7hlKe2ZGojYmZsGUCsqygvOnf6FZsbrtm3bY+wUigiAIC/funlXR0RXYgv/BzAmGn979qGvXyOALghAJQAtAB0A/fIrDY6MNurj/LBqADW8OFYACQB4+2d80or7Ra0ZtxAAAAABJRU5ErkJggg=="/>
		</div>
		<h1>
			<?php echo $errorInfo['text'].(($errorInfo['code']) ?' ['.$errorInfo['code'].']' :'').' | '.$errorInfo['name']; ?>
		</h1>
		<h2 id="message">
			<?php echo str_replace("\n", '<br />', $errorInfo['message']) ?>
		</h2>
		<h2>stack trace</h2>
		<ul>
			<li><?php echo implode('</li><li>', $errorInfo['traces']) ?></li>
		</ul>
		<table cellpadding="5">
			<tr>
				<td style="font-size:12px;font-weight:bold;">PHP Version</td>
				<td>&nbsp;</td>
				<td><?php echo phpversion(); ?></td>
			</tr>
			<tr>
				<td style="font-size:12px;font-weight:bold;">Zamp PHP Version</td>
				<td>&nbsp;</td>
				<td><?php echo Zamp\VERSION; ?></td>
			</tr>
			<tr>
				<td style="font-size:12px;font-weight:bold;">Operating System</td>
				<td>&nbsp;</td>
                <td>
                <?php
                    $user = posix_getpwuid(posix_geteuid());
                    echo php_uname() .' (User: '.$user['name'].')';
                ?>
                </td>
			</tr>
			<tr>
				<td style="font-size:12px;font-weight:bold;">Generated Time</td>
				<td>&nbsp;</td>
				<td><?php echo Zamp\Core::system()->systemTime('jS M Y, h:i:s a', $errorTimeDiffFromGmt).' (GMT '.$timeZoneFormat.')'; ?></td>
			</tr>
		</table>
		<br/>
		<h2>Additional Information</h2>
		<a href="#" onClick="toggle('_Server');return false;">$_SERVER</a>
		&nbsp;&nbsp;&nbsp;
		<a href="#" onClick="toggle('_Get');return false;">$_GET</a>
		&nbsp;&nbsp;&nbsp;
		<a href="#" onClick="toggle('_Post');return false;">$_POST</a>
		&nbsp;&nbsp;&nbsp;
		<a href="#" onClick="toggle('_Env');return false;">$_ENV</a>
		&nbsp;&nbsp;&nbsp;
		<a href="#" onClick="toggle('_Request');return false;">$_REQUEST</a>
		<table cellpadding="5" id="_Server" style="display:none;">
			<tr>
				<td align="left">
					<pre>
						<?php var_dump($_SERVER); ?>
					</pre>
				</td>
			</tr>
		</table>
		<table cellpadding="5" id="_Get" style="display:none;">
			<tr>
				<td align="left">
					<pre>
						<?php var_dump(Zamp\Core::system()->request->get()); ?>
					</pre>
				</td>
			</tr>
		</table>
		<table cellpadding="5" id="_Post" style="display:none;">
			<tr>
				<td align="left">
					<pre>
						<?php var_dump($_POST); ?>
					</pre>
				</td>
			</tr>
		</table>
		<table cellpadding="5" id="_Env" style="display:none;">
			<tr>
				<td align="left">
					<pre>
						<?php var_dump($_ENV); ?>
					</pre>
				</td>
			</tr>
		</table>
		<table cellpadding="5" id="_Request" style="display:none;">
			<tr>
				<td align="left">
					<pre>
						<?php var_dump($_REQUEST); ?>
					</pre>
				</td>
			</tr>
		</table>
	</div>
	powered by <a href="http://www.zampphp.org" target="_blank" style="text-decoration:none;color:brown">Zamp PHP</a>
</center>
</body>
</html>
