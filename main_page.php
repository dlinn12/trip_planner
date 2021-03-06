<?php

// need map

// need to be able to enter constraints (area)

// need to be able to search locations in area (text input queries google api)

// need place to enter locations you would like to see (textual input)

// need to be able to add locations (add button)

// need to be able to add additional constraints to each location (time there)

// need to be able to update map to show locations
$key = "AIzaSyDrf1CoJf5si6S2jo7_hxNKELjZgFBlIPk";

$userid = $_GET['userid'];
if (isset($_GET['tripname'])) {
    $trip_name = $_GET['tripname'];
} else {
    $trip_name = "";
}
?>

<html>
    <head>

        <link rel="stylesheet" href="CssStuff.css">
        <script src="jquery-3.2.1.min.js"></script>
        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDrf1CoJf5si6S2jo7_hxNKELjZgFBlIPk&callback=initMap"
    async defer></script>
        <style>

            #map {
                height: 500px;
                width: 500px;
                right: 50px;
            }
        </style>

        <script>

        var addedLocations = [];
        var tripCords = [];
        function debug(str) {
            <?php
            if (isset($_GET['debug'])) {
                echo "console.log(str);";
            }
            ?>
        }

        <?php
        $lat = 35.1189;
        $lon = -89.9368;
        ?>
        var map = null;
        var markers = [];
        var totalDistance = 0;
        var tracedPath = null;

        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 11,
                center: {lat: <?php echo $lat; ?>, lng: <?php echo $lon; ?>}
            });
        }

        //called in tail end of addLocation() after parseLocationDetails() has been called
        //Could be moved to better spot
        function tracePath() {
            var coordinates = [];
            //add the coordinates for every location in addedLocations into coordinates array
            addedLocations.forEach(function(p) {
                if(!p.isSkipped) {
                    coordinates.push({lat: p.latitude, lng: p.longitude})
                }
            });

            // if a path is already on the map, remove it before drawing the new one.
            if(tracedPath != null) {
                tracedPath.setMap(null);
            }
            //creates the polyline with the path specified by coordinates array
            tracedPath = new google.maps.Polyline({
                path: coordinates,
                geodesic: true,
                strokeColor: '#0000FF',
                strokeOpacity: 1.0,
                strokeWeight: 2
            });
            //give the map to the polyline object
            tracedPath.setMap(map);
         }

        function getLocations() {
            var query = document.getElementById('search').value;
            var cityState = document.getElementById('cityState').value;
            var query = query + ' in ' + cityState;
            var radius = parseInt(document.getElementById('radius').value) * 1609;

            // have a max of 50000 meters in the google API radius
            if (radius && radius < 50000) {
                    $.ajax({
                    url: 'http://localhost/endpoints/get_locations.php?query=' + query + '&radius=' + radius, 
                    type: "GET",   
                    cache: false,
                    success: parseLocations,
                    error: function(err) {
                        debug(err);
                    }
                });
            } else {

                //todo - show user an error here...
                console.log('radius too large!');
            }
        }

        // expects an array of locations
        // dont edit css, find way to call border, cry
        function parseLocations(locations) {
            //debug(locations);
            locations = JSON.parse(locations);
            locations = locations.results;
            var parent = document.getElementById("locations");
            // clear list of child nodes
            while (parent.hasChildNodes()) {
                parent.removeChild(parent.lastChild);
            }
            var sort = document.getElementById("sortOrder");
            if (sort.selectedIndex === 1) {
                sortByRating(locations);
            }
            // we deleted the h4, so we need to put it back
            var h = document.createElement("h3");
            var text = document.createTextNode("Search Results");
            h.appendChild(text);
            parent.appendChild(h);
            locations.forEach(function (location) {
                //debug(location.formatted_address);
                var attractiondiv = document.createElement("div");
                attractiondiv.setAttribute("class", "border");
                var locationName = document.createElement("p");
                parent.appendChild(attractiondiv);
                var node = document.createTextNode("Name: " + location.name);
                locationName.appendChild(node);
                var locationAddress = document.createElement("p");
                node = document.createTextNode("Address: " + location.formatted_address);
                locationAddress.appendChild(node);
                if (location.rating) {
                    var locationRating = document.createElement("p");
                    node = document.createTextNode("Rating: " + location.rating);
                    locationRating.appendChild(node);
                } else {
                    var locationRating = document.createElement("p");
                    node = document.createTextNode("Rating currently unavaiable.");
                    locationRating.appendChild(node);
                }
                var addButton = document.createElement("BUTTON");
                var locationId = location.place_id;
                //debug(locationId);
                //addButton.setAttribute("id", locationId);
                addButton.setAttribute('onclick','addLocation(\'' + locationId + '\')');
                addButton.setAttribute('name', locationId);
                node = document.createTextNode("Add Location");
                addButton.appendChild(node);
                attractiondiv.appendChild(locationName);
                attractiondiv.appendChild(locationAddress);
                attractiondiv.appendChild(locationRating);
                attractiondiv.appendChild(addButton);
            });
        }

        function addLocation(locationId,priority) {
            debug('Got location! ' + locationId);
            debug('http://localhost/endpoints/get_location_data.php?placeid=' + locationId);
            $.ajax({
                url: 'http://localhost/endpoints/get_location_data.php?placeid=' + locationId,
                type: "GET",
                cache: false,
                async: false,
                success: function(result) {
                    parseLocationDetails(result, priority);
                },
                error: function(err) {
                    debug(err);
                }
            });
            tracePath();
        }

        function parseLocationDetails(place, priority) {
            //debug('got place ' + place);
            place = JSON.parse(place).result;

            var rating = 99;
            if (place.rating) {
                rating = place.rating;
            }

            var p = {
                id: place.place_id,
                name: place.name,
                icon: place.icon,
                url: place.url,
                formatted_address: place.formatted_address,
                longitude: place.geometry.location.lng,
                latitude: place.geometry.location.lat,
                rating: place.rating,                        // default value of 99
                opening_hours: place.opening_hours,
                isSkipped: false
            }

            // console.log("priority check: " + priority);
            if(priority && priority == 127) {
                p.isSkipped = true;
            }

            if (map == null) {
                initMap();
            }
            map.setCenter({ lat: p.latitude, lng: p.longitude});

            var marker = new google.maps.Marker({
                map: map,
                position: {
                    lat: p.latitude,
                    lng: p.longitude
                },
                title: p.name,
                id: p.id
            });
            if(p.isSkipped) {
                marker.setVisible(false);
            }
            markers.push(marker);
            addedLocations.push(p);

            // at this point, we can consider this place to be the last in the list? 
            // let's assume that and try to build the total distance. 
            if (addedLocations.length > 1) {
                //debug('length of array greater than 1');
                var orig = addedLocations[addedLocations.length - 1].id;
                var dest = addedLocations[addedLocations.length - 2].id;
                debug('http://localhost/endpoints/get_distance.php?origplaceid=' + orig + '&destplaceid=' + dest);
                $.ajax({
                    url: 'http://localhost/endpoints/get_distance.php?origplaceid=' + orig + '&destplaceid=' + dest,
                    type: "GET",
                    cache: false,
                    //async: false,
                    success: function(result) {
                        parseDistanceVal(result);
                    },
                    error: function(err) {
                        debug(err);
                    }
                });
            }

            var parent = document.getElementById("itineraryList");
            // addedLocations.forEach(function(result) {

            //     console.log('adding to list', result);
            //     // if the element doesn't already exist
            //     // on the page, add it!

                //debug(result)

            // element doesn't already exist
            //debug('Element does not exist!! ' + JSON.stringify(p.name));

            if (!document.getElementById(p.id)) {
                var userLocation = document.createElement("h5");
                userLocation.setAttribute('id', p.id);
                var node = document.createTextNode("Location: " + p.name);
                userLocation.appendChild(node);
                userLocation.setAttribute("class", "locationNameClass");
                parent.appendChild(userLocation);
                
                //console.log("priority for " + p.name " is " + priority);
                if (priority && priority == 127) {
                    userLocation.setAttribute('class','skipped');
                }

                var lineBreak = document.createElement("br");
                parent.appendChild(lineBreak);

                var icon = document.createElement("img");
                icon.setAttribute("src", p.icon);
                icon.setAttribute("class", "iconClass");
                parent.appendChild(icon);

                var lineBreak = document.createElement("br");
                parent.appendChild(lineBreak);

                if (p.url) {
                    var website = document.createElement("a");
                    node = document.createTextNode("See more information");
                    website.appendChild(node);
                    website.setAttribute("href", p.url);
                    parent.appendChild(website);
                }


                var lineBreak = document.createElement("br");
                parent.appendChild(lineBreak);


                // if the API returned hours of operation
                if (p.opening_hours) {
                    var operation = document.createElement("p");
                    node = document.createTextNode("Hours of Operation");
                    operation.appendChild(node);
                    parent.appendChild(operation);

                    p.opening_hours.weekday_text.forEach(function(weekday) {
                        var hours = document.createElement("p");
                        hours.setAttribute("class", "weekdayClass");
                        node = document.createTextNode(weekday);
                        hours.appendChild(node);
                        parent.appendChild(hours)
                    })
                } else {
                    var operation = document.createElement("p");
                    node = document.createTextNode("Hours of Operation Not Available at this time");
                    operation.appendChild(node);
                    parent.appendChild(operation);
                }

                var skipButton = document.createElement("button");
				skipButton.setAttribute('onclick','skip(\'' + p.id + '\')');
				var t = document.createTextNode("Skip Location");
                skipButton.appendChild(t);
                parent.appendChild(skipButton);
            }
            //});
        }

        function parseDistanceVal(result) {
            result = JSON.parse(result).rows[0].elements[0].distance;
            totalDistance += result.value;
            console.log(result);

            document.getElementById('total').innerHTML = (totalDistance * 0.0006) + " miles" ;
        }

        //saves the list trip to database
        //stop execution and alert user if trip name is not set
        function saveList() {
            debug(addedLocations);
            var listName = document.getElementById('list_name').value;
            console.log(listName);
            if (listName === "") { 
                alert("You must enter a name for your trip!");
                return;
            }
            var tripObject = {
                'userid': <?php echo $userid; ?>,
                'trip_name': listName,
                'trip': addedLocations
            }
            debug(JSON.stringify(tripObject));
            $.ajax({
                url: 'http://localhost/endpoints/update_trip.php',
                type: "POST",
                data: JSON.stringify(tripObject),
                contentType: "application/json",
                success: function(result) {
                    debug('added list to db');
                    if (result) {
                        var tripData = JSON.parse(result);
                        if (tripData && tripData.trip_id) {
                            window.location.replace(window.location.href + '&tripid=' + tripData.trip_id);
                        }    
                    }
                },
                error: function(err) {
                    console.log('Error adding list');
                }
            });
        }

        function loadList(tripId) {
            //debug('loading list! for trip id ' + tripId);
            $.ajax({
                url: 'http://localhost/endpoints/query_attractions.php?tripid=' + tripId + '&userid=' + '<?php echo $userid; ?>', 
                type: "GET",   
                cache: false,
                success: function(result) {
                    result = JSON.parse(result);
                    result.forEach(function(place) {
                        //console.log("p.id: " + place.place_id + " priority " + place.priority);
                        addLocation(place.place_id, place.priority);
                    });
                },
                error: function(err) {
                    debug(err);
                }
            });

        }

        // This skips an attraction on the list, but does not permanently remove it.
        // A skipped attraction is not considered when computing a route.
        function skip(pdiddy) {
            //accessing addLocations array to obtain Google place id
			for(key in addedLocations)	{
				key = addedLocations[key];
				// if the id matches, then we skip this location
				if(key.id == pdiddy) {
					key.isSkipped = true;
				}
			}
            //var url = window.location.href;
            // var index1 = url.search("tripid=");
            var tripId = <?php echo $_GET['tripid']; ?>;
            // if you have not saved your trip, there is no tripid and we have nothing
            // in the database to interact with at the moment. search returns -1 if not found.
            //if(index1 >= 0) {
            if(tripId) {
				// place.id from attraction in array
                var placeId = pdiddy; //place_id
                // var index2 = url.substring(index1).search("&");
				// trip.id from url where 'tripid='up to '&'
                //var tripId = url.substring(index1+7);
                /*if (index2) {
                    tripId = url.substring(index1+7, index1+index2);
                }*/
				console.log(tripId);
                $.ajax({
                    url: "http://localhost/endpoints/skip_location.php",
                    type: "POST",
                    data: {
                        place_id: placeId,
                        trip_id: tripId,
                    },
                    //this should cause a visual update
                    success: function(greyOut) {
						var section = document.getElementsByTagName("h5");
						console.log(section);
						for(i = 0; i < section.length; i++) {
							if(section[i].id == pdiddy) {
                                // this crosses out the place name and turns it grey
                                section[i].setAttribute("class", "skipped");
                                // make the corresponding map marker invisible
                                for(i = 0; i < markers.length; i++) {
                                    if(markers[i].id == pdiddy) {
                                        markers[i].setVisible(false);
                                        tracePath();
                                        break;
                                    }
                                }
							}
						}
                    },
                    error: function(err) {
                        console.log("Error skipping location.");
                    }
                });
            }
        }

        function optimizeTrip() {
            debug('Optimizing trip... ');
            if (addedLocations.length > 2) {
                debug(JSON.stringify(addedLocations));
                $.ajax({
                    url: "http://localhost/endpoints/optimize_trip.php",
                    type: "POST",
                    data: JSON.stringify(addedLocations),
                    //this should cause a visual update (red or grey background for skipped?)
                    success: function(result) {
                        // just refresh the page with the new database update. 
                        //window.location.replace(window.location.href);
                        

                        result = JSON.parse(result);
                        var pids = {};
                        
                        // gapi doesn't return the placeids in their order
                        // need to keep track of that. 
                        addedLocations.forEach(function(place) {
                            if(!place.isSkipped) {
                                pids[place.id] = {};
                            }
                        });

                        console.log(pids);

                        var rows = result.rows;
                        var keys = Object.keys(pids);
                        for (var i = 0; i < keys.length; i++) {
                           for (var j = 0; j < keys.length; j++) {
                              // ignore distance to self
                              if (keys[i] != keys[j]) {
                                pids[keys[i]][keys[j]] = rows[i].elements[j].distance.value;
                              }
                           }
                        }
                        

                        console.log('The shortest path is!!!');
                        console.log(JSON.stringify(pids));
                        $.ajax({
                            url: "http://localhost/endpoints/relay.php",
                            type: "POST",
                            data: JSON.stringify(pids),
                            success: function(result) {
                                result = result.substring(1, result.length-1)
                                console.log(result);
                                var optimizedObject = "";
                                for (var i = 0; i < result.length; i++) {
                                    if (result[i] != "\\") {
                                        optimizedObject += result[i];
                                    }
                                }
                                
                                optimizedObject = JSON.parse(optimizedObject);
                                
                                <?php
                                 if (isset($_GET['tripid'])) {
                                    echo 'optimizedObject.tripId = ' . $_GET['tripid'] . ";"; 
                                 } else {
                                    echo 'optimizedObject.tripId = 99999;'; 
                                 }
                                
                                ?>
                                console.log(JSON.stringify(optimizedObject));

                                // now I need to update the db with the new list.
                                $.ajax({
                                    url: "http://localhost/endpoints/update_trip_by_id.php",
                                    type: "POST",
                                    data: JSON.stringify(optimizedObject),
                                    success: function(result) {
                                        console.log(result);
                                        // we'll just refresh the page to force a redraw
                                        window.location.replace(window.location.href);
                                    },
                                    error: function(err) {
                                        console.log(err);
                                    }
                                });

                            }, 
                            error: function(err) {
                                console.log('err finding shortest path', err);
                            }
                        });
                    },
                    error: function(err) {
                        console.log("Error skipping location.");
                    }
                });
            } 
        }

        //locations: array of locations
        function sortByRating (locations) {
            locations.sort(function(a,b){return b.rating - a.rating})
        }

        </script>


        <style>
            #locations {
                float: left;
            }

            #itineraryList {
                float: right;
                
            }
            /* Map */
            #map {
                float: right;
                    
            }
            h5 {
                border: 2px solid black;
                padding: 2px;
            }
            .border {
                border: 2px solid black;
                padding: 2px;
            }
            .iconClass {
                height: 15px;
                width: 15px;
            }

            .weekdayClass {
                padding-left: 4px;
                margin: 0px;
            }

            .locationNameClass {
                margin: -13px;
                margin-top: 10px;
            }

            .skipped {
                color: gray;
                text-decoration: line-through;
            }

            .column {
                float: left;
            }

            .columnmiddle {
                width: 40%;
            }

            .columnside{
                width: 20%;
            }



        </style>
    </head>

    <?php

        if (isset($_GET['tripid'])) {
            // if the trip id is already set, then we want to update the page
            // to show the locations already on that trip. Everything else should 
            // stay the same
            $tripid = $_GET['tripid'];
            echo '<body onload="loadList(' . $tripid . ')">';
        } else {
            echo '<body>';
        }
    ?>
        <div class="jumbotron">
        <div class="container sight-seer">
         Sight Seer
        </div>
    </div>
<!-- Navigation Bar -->
<header>
    <div class="navigation">
        <ul>
            <li class="Info"><a href="#">Info</a></li>
            <li class="Dashboard"><a class="active" href="http://localhost/main_page.php">Dashboard</a></li>
            <li class="Trips"><a href="http://localhost/my_trips_page.php">My Trips</a></li>
            <li class="Login"><a href="http://localhost/login_page.php">Login</a></li>
            <li class="Signup"><a href="http://localhost/login_page.php">Sign Up</a></li>
            <li class="Account"><a href="#">Account</a></li>
        </ul>
    </div>
</header>
<!-- 
        Type: <input type="text" id="type" value="Bar"></br>
        Latitude: <input type="text" id="latitude" value="35"></br>
        Longitude: <input type="text" id="longitude" value="-90"></br>
        Radius: <input type="text" id="radius" value="1000"></br> -->

        Search Places: <input type="text" id="search" value="Tourist Attractions"></br>
        City/State: <input type="text" id="cityState" value="Memphis, TN"></br>
        Radius (miles): <input type="text" id="radius" value="10"></br>
        
        Order of Results: <select id="sortOrder">
            <option value="popularity" selected>Popularity</option>
            <option value="rating">Rating</option>
        </select></br>
        <script>
        //$(document).ready(function(){
         //    $( getLocations() ).onclick( function() {
          //      $("p").wrapAll(createElement("<div class='border' />"));
          // } );
         //});
        </script>
        <button onclick='getLocations()' id="attractions_button">Find Attractions</button></br>
        Total Distance: <span id="total"></span><br>
        <button onclick="optimizeTrip()" id="optimize_button">Optimize Your Trip</button>
        <div class="columnside">    
            <div id="locations">
                <h3>Search Results</h3>
            </div>

        
        </div>
        <div class="columnside" id="itineraryList">
            <h3>Added Locations</h3>
        </div>
        <div id="svbtn">
            Trip Name: <input type="text" id="list_name" value="<?php echo $trip_name; ?>"></input>
            <button onclick="saveList()" id="save_button">Save Your Trip</button>
        </div>

        <div class="columnmiddle" id="map" onload="initMap()">
        </div>
    </body>
</html>
