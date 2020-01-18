# Dugnadssystemet

## Utvikling

Ved hjelp av Docker og Docker Compose kan man kjøre opp systemet lokalt:

```bash
docker-compose up --build
```

Åpne http://localhost:8080/dugnaden/

Pålogging vil fungere mot foreningenbs.no ut av boksen (merk at man må være
i gruppen "dugnaden" for å kunne benytte admin-sidene).

Admin-sidene finnes under:

http://localhost:8080/dughttp://localhost:8080/dugnaden/naden/index.php?do=admin

### Database under utvikling

Dersom man utfører database-endringer må disse utføres manuelt i produksjon.
For å laste inn blank database på nytt må man først slette database-volumet,
slik at den laster inn database på nytt ved neste oppstart:

```bash
docker-compose stop database
docker-compose rm -v database
```

## Produksjon

For å sette en ny versjon i produksjon:

Lag nytt Docker image og last opp til Docker Hub:

```bash
./build-and-push.sh
```

Benytt oppsettet i https://github.com/blindern/drift/tree/master/ansible
til å sette ny versjon i produksjon (oppdater Docker image referanse i
`site.yml` og rull ut oppdatering).
