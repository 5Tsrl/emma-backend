## WeasyPrint  
Per la generazione dei PDF usiamo Weasyprint, un tool che permette di generare PDF da HTML e CSS.

### Installazione
Per installare WeasyPrint è necessario installare i seguenti pacchetti:
- python3-dev
- python3-pip
- python3-cffi
- libcairo2
- libpango1.0-0
- libgdk-pixbuf2.0-0
- libffi-dev
- shared-mime-info

Per installare i pacchetti eseguire il seguente comando:
```bash
sudo apt-get install python3-dev python3-pip python3-cffi libcairo2 libpango1.0-0 libgdk-pixbuf2.0-0 libffi-dev shared-mime-info python3.10-venv
```

Per installare WeasyPrint eseguire il seguente comando:
```bash
python3 -m venv venv
source venv/bin/activate
pip install weasyprint
weasyprint --info
```

### Aggiunta in path
Per aggiungere WeasyPrint in path eseguire il seguente comando:
```bash 
sudo ln -s /home/massimoi/venv/bin/weasyprint /usr/bin/weasyprint
```
