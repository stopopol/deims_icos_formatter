<?php

$query_string = "

# query to return total number of datasets per site as well as latest five datasets
# landing page for all datasets https://data.icos-cp.eu/portal/#%7B%22filterCategories%22%3A%7B%22station%22%3A%5B%22iES_SE-Sto%22%5D%7D%7D


PREFIX cpmeta: <http://meta.icos-cp.eu/ontologies/cpmeta/>
PREFIX prov:   <http://www.w3.org/ns/prov#>
PREFIX rdfs:   <http://www.w3.org/2000/01/rdf-schema#>
PREFIX xsd:    <http://www.w3.org/2001/XMLSchema#>

SELECT 	(?dobj AS ?url) 
		?datasetTitle 
		?total
WHERE {
  # Subquery to get the total count
  {
    SELECT (COUNT(?allDobj) AS ?total)
    WHERE {
      ?spec cpmeta:hasDataLevel [] ;
            cpmeta:hasAssociatedProject ?project .
      FILTER(STRSTARTS(STR(?spec), 'http://meta.icos-cp.eu/'))
      FILTER NOT EXISTS {
        ?project cpmeta:hasHideFromSearchPolicy 'true'^^xsd:boolean
      }

      ?allDobj cpmeta:hasObjectSpec ?spec ;
               cpmeta:wasAcquiredBy/prov:wasAssociatedWith <http://meta.icos-cp.eu/resources/stations/" . $icos_station_code . "> .

      FILTER NOT EXISTS { [] cpmeta:isNextVersionOf ?allDobj }
    }
  }

  # Latest 5 dataset titles logic
  {
    SELECT ?dobj ?datasetTitle
    WHERE {
      VALUES ?station { <http://meta.icos-cp.eu/resources/stations/" . $icos_station_code . "> }

      ?spec cpmeta:hasDataLevel [] ;
            rdfs:label ?datasetTitle .

      FILTER STRSTARTS(STR(?spec), 'http://meta.icos-cp.eu/')

      OPTIONAL {
        ?spec cpmeta:hasAssociatedProject ?proj .
        FILTER(?proj != cpmeta:hasHideFromSearchPolicy || 
               NOT EXISTS { ?proj cpmeta:hasHideFromSearchPolicy 'true'^^xsd:boolean })
      }

      ?dobj cpmeta:hasObjectSpec ?spec ;
            cpmeta:wasAcquiredBy/prov:wasAssociatedWith ?station ;
            cpmeta:wasSubmittedBy/prov:endedAtTime ?submTime .

      FILTER NOT EXISTS { [] cpmeta:isNextVersionOf ?dobj }
    }
    ORDER BY DESC(?submTime)
    LIMIT 5
  }
}

";
