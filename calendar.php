<?php

/*
**************************************************************************************************************************
** CORAL Licensing Module v. 1.0
**
** Copyright (c) 2010 University of Notre Dame
**
** This file is part of CORAL.
**
** CORAL is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
**
** CORAL is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License along with CORAL.  If not, see <http://www.gnu.org/licenses/>.
**
**************************************************************************************************************************

** This page was originally intended as a standalone add-on.  After interest this was added to the Licensing module
** but it was not retrofitted to more tightly integrate into the Licensing module.

*/

include_once 'directory.php';

$pageTitle='Home';
include 'templates/header.php';

//used for creating a "sticky form" for back buttons
//except we don't want it to retain if they press the 'index' button
//check what referring script is

if (isset($_SESSION['ref_script']) && ($_SESSION['ref_script'] != "license.php")){
	$reset='Y';
}else{
	$reset='N';
}

$_SESSION['ref_script']=$currentPage;

//below includes search options in left pane only - the results are refreshed through ajax and placed in div searchResults

//print header
$pageTitle='Calendar';

$config = new Configuration;

$host = $config->database->host;
$username = $config->database->username;
$password = $config->database->password;
$license_databaseName = $config->database->name;
$resource_databaseName = $config->settings->resourcesDatabaseName;

$linkID = mysql_connect($host, $username, $password) or die("Could not connect to host.");
mysql_select_db($license_databaseName, $linkID) or die("Could not find License database.");
mysql_select_db($resource_databaseName, $linkID) or die("Could not find Resource database.");

$display = array();
$calendarSettings = new CalendarSettings();
$calendarSettingsArray = $calendarSettings->allAsArray();

// Set defaults just incase

$daybefore = "30";
$dayafter = "1460";
$resourceType = NULL;		// Resource Type ID
$authorizedSiteID = array();  // Site ID's 1,2,3 etc

	foreach($calendarSettingsArray as $display) {
		if (strtolower($display['shortName']) == strtolower('Days Before')) {
			if (strlen($display['value'])>0) {
				$daybefore = $display['value'];
			}
		} elseif (strtolower($display['shortName']) == strtolower('Days After')) {
			if (strlen($display['value'])>0) {
				$dayafter = $display['value'];
			}
		} elseif (strtolower($display['shortName']) == strtolower('Resource Type')) {
			if (strlen($display['value'])>0) {
				$resourceType = $display['value'];
			}
		} elseif (strtolower($display['shortName']) == strtolower('Authorized Site')) {
			if (strlen($display['value'])>0) {
				$authorizedSiteID = preg_split("/[\s,]+/", $display['value']);
			}
		}
	}
	
echo "<!-- Start minus current day $daybefore End plus current day $dayafter-->";

$query = "
SELECT DATE_FORMAT(`$resource_databaseName`.`Resource`.`subscriptionEndDate`, '%Y') AS `year`, 
DATE_FORMAT(`$resource_databaseName`.`Resource`.`subscriptionEndDate`, '%M') AS `month`, 
DATE_FORMAT(`$resource_databaseName`.`Resource`.`subscriptionEndDate`, '%y-%m-%d') AS `sortdate`, 
DATE_FORMAT(`$resource_databaseName`.`Resource`.`subscriptionEndDate`, '%m/%d/%Y') AS `subscriptionEndDate`, 
`$resource_databaseName`.`Resource`.`resourceID`, `$resource_databaseName`.`Resource`.`titleText`,  
`$license_databaseName`.`License`.`shortName`, 
`$license_databaseName`.`License`.`licenseID`, `$resource_databaseName`.`ResourceType`.`shortName` AS resourceTypeName, `$resource_databaseName`.`ResourceType`.`resourceTypeID` 
FROM `$resource_databaseName`.`Resource` 
INNER JOIN `$resource_databaseName`.`ResourceLicenseLink` ON (`$resource_databaseName`.`Resource`.`resourceID` = `$resource_databaseName`.`ResourceLicenseLink`.`resourceID`) 
INNER JOIN `$license_databaseName`.`License` ON (`ResourceLicenseLink`.`licenseID` = `$license_databaseName`.`License`.`licenseID`) 
INNER JOIN `$resource_databaseName`.`ResourceType` ON (`$resource_databaseName`.`Resource`.`resourceTypeID` = `$resource_databaseName`.`ResourceType`.`resourceTypeID`) 
WHERE 
`$resource_databaseName`.`Resource`.`subscriptionEndDate` IS NOT NULL AND 
`$resource_databaseName`.`Resource`.`subscriptionEndDate` <> '00/00/0000' AND 
`$resource_databaseName`.`Resource`.`subscriptionEndDate` BETWEEN (CURDATE() - INTERVAL " . $daybefore . " DAY) AND (CURDATE() + INTERVAL " . $dayafter . " DAY) ";

if ($resourceType) {
	$query = $query . " AND `$resource_databaseName`.`Resource`.`resourceTypeID` IN ( ". $resourceType . " ) ";
}

$query = $query . "ORDER BY `sortdate`, `$resource_databaseName`.`Resource`.`titleText`";

$result = mysql_query($query, $linkID) or die("Bad Query Failure");

?>

<div style='text-align:left;'>
	<table class="headerTable" style="background-image:url('images/header.gif');background-repeat:no-repeat;">
		<tr style='vertical-align:top;'>
			<td>
				<b>Upcoming License Renewals</b>
			</td>
		</tr>
	</table>
	
	<div id="searchResults">
		<table style="width: 100%;" class="dataTable">
			<tbody>	
			<?php
				$mYear = "";
				$mMonth = "";
				$i = -1;
				
				while ($row = mysql_fetch_assoc($result)) {
					$query2 = "SELECT 
					  `$resource_databaseName`.`Resource`.`resourceID`,
					  `$resource_databaseName`.`AuthorizedSite`.`shortName`,
					  `$resource_databaseName`.`AuthorizedSite`.`authorizedSiteID`
					FROM
					  `coral_resources_prod`.`Resource`
					  INNER JOIN `$resource_databaseName`.`ResourceAuthorizedSiteLink` ON (`$resource_databaseName`.`Resource`.`resourceID` = `$resource_databaseName`.`ResourceAuthorizedSiteLink`.`resourceID`)
					  INNER JOIN `$resource_databaseName`.`AuthorizedSite` ON (`$resource_databaseName`.`ResourceAuthorizedSiteLink`.`authorizedSiteID` = `$resource_databaseName`.`AuthorizedSite`.`authorizedSiteID`)
					WHERE
					  `$resource_databaseName`.`Resource`.`resourceID` = " . $row["resourceID"] .
					  " order by `$resource_databaseName`.`AuthorizedSite`.`shortName`";

					$result2 = mysql_query($query2, $linkID) or die("Bad Query Failure");
					  
					$i = $i + 1;
					
					if ($mYear != $row["year"]) {
						$mYear = $row["year"];
						echo "<tr>";
						echo "<th colspan='2'><table class='noBorderTable'><tbody><tr><td>" . $mYear . "</td></tr></tbody></table></th>";
						echo "</tr>";
					}	
					
					if ($mMonth != $row["month"]) {
						$mMonth = $row["month"];
						echo "<th colspan='2'><table class='noBorderTable'><tbody><tr><td>&nbsp;&nbsp;&nbsp;" . $mMonth . "</td></tr></tbody></table></th>";
					}
					
					$html = "<tr>";
				
					if ($i % 2 == 0) {
						$alt = "alt";
					} else {
						$alt = "";
					}
					
					$html = $html . "<td  colspan='2' class='$alt'>";
					
					$html = $html . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href='../resources/resource.php?resourceID=" . $row["resourceID"] . "'><b>". $row["titleText"] . "</b></a>";
					$html = $html . "&nbsp;&nbsp;[License: ";
					$html = $html . "<a href='license.php?licenseID=" . $row["licenseID"] . "'>". $row["shortName"] . "</a>";
					$html = $html . " ] - " . $row["resourceTypeName"] . "<!-- ( TypeID=" . $row["resourceTypeID"] . ") -->" ;
					
						$k = 0;
						$siteID = array();
						
						while ($row2 = mysql_fetch_assoc($result2)) {
							if ($k == 0) {
								$html = $html . "</td></tr>";
								$html = $html . "<tr>
									<td class='$alt'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
									<td class='$alt'>Participants:  ";
							} else {
								$html = $html . ", ";
							}
							
							$html = $html . $row2["shortName"] . "<!-- ( SiteID=" . $row2["authorizedSiteID"] . ") -->";
							array_push( $siteID, $row2["authorizedSiteID"] );
							$k = $k + 1;
						}
					
					$html = $html . "</td>";

					$html = $html . "</tr>";
					
					$arr3 = array_intersect($authorizedSiteID, $siteID);
					
					if (count($authorizedSiteID) == 0) {
						echo $html;
					} elseif (count($arr3) > 0) {
						echo $html;
					}
				}
			?>	
			</tbody>
		</table>
	</div>	
</div>
<br />

<?php

  //print footer
  include 'templates/footer.php';
?>