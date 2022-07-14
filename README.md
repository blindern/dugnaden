# Dugnadssystemet

https://foreningenbs.no/dugnaden/

**Merk**: Det er påbegynt en omskriving av dette for å modernisere oppsettet
og åpne opp får å enklere videreutvikle systemet. Se `modernize` branchen:
https://github.com/blindern/dugnaden/tree/modernize

## Anretningsdugnader

Slik gjorde vi det vår 2017:

1. Alle ble lagt inn i systemet og tildelt lørdagsdugader, dugnadsfri ble lagt inn osv
2. La inn alle anretningsdugnadene som dugnader (se [regneark](https://docs.google.com/spreadsheets/d/1gGBnQDiAIyp2VT79-YFtVX4ToYwmxFM46n1HCcs8fS8/edit?usp=sharing))
3. Hentet ut liste over de som skulle få anretnignsdugnad (se under)
4. Hentet ut liste over ID-ene til anretningsdugnadene som var lagt inn
5. Mappet de to listene over og oppdaterte dugnaden fra lørdagsdugnad til anretningsdugnad ved å kjøre en update-spørring per rad

### Generere liste over lørdagsdugnader som skal byttes til anretningsdugnader

Endre 47 til det antall personer som vi skal hente liste over. Med 47 blir det altså 94 rader.

```sql
SELECT s.deltager_id
FROM (
  SELECT deltager_beboer, RAND() x FROM (
    SELECT deltager_beboer, COUNT(deltager_id) ant
    FROM bs_deltager
    GROUP BY deltager_beboer
    HAVING ant = 2
  ) r
  ORDER BY RAND()
  LIMIT 47
) r2
JOIN bs_deltager s ON r2.deltager_beboer = s.deltager_beboer
ORDER BY r2.x
```

### Hente liste over anretningsdugnadene

```sql
SET lc_time_names = 'nb_NO';
SELECT
  /*DATE_FORMAT(dugnad_dato, '%a'),*/
  DATE(dugnad_dato),
  CONCAT(beboer_for, ' ', beboer_etter)
FROM
  bs_deltager
  JOIN bs_beboer ON deltager_beboer = beboer_id
  JOIN bs_dugnad ON deltager_dugnad = dugnad_id
WHERE dugnad_type = 'anretning'
ORDER BY dugnad_dato
```


### Hente liste over antall per lørdagsdugnad

```sql
SELECT
  DATE(dugnad_dato),
  COUNT(deltager_beboer)
FROM
  bs_dugnad
  LEFT JOIN bs_deltager ON deltager_dugnad = dugnad_id
  LEFT JOIN bs_beboer ON deltager_beboer = beboer_id
WHERE dugnad_type = 'lordag' AND dugnad_slettet = 0
GROUP BY dugnad_dato
ORDER BY dugnad_dato
```
