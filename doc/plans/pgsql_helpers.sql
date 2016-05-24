CREATE OR REPLACE FUNCTION match_xml_nodes(VARCHAR, XML) RETURNS BOOLEAN AS
$$
SELECT CASE WHEN cast(xpath($1, $2) as text[]) != '{}'
THEN true ELSE false END;
$$ LANGUAGE 'sql' IMMUTABLE;
