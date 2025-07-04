# Generate image from Dockerfile
# ===========================

# Generate ssh key
```bash
ssh-keygen -t ed25519 -C "deploy-key"
```

# Build image from Dockerfile
```bash
docker build -t 5tapi ../5t.api
docker build -t 5tvue ../5t.vue

```
# Stop apache
```bash
sudo service apache2 stop
```
# Stop cron
```bash
sudo service cron stop
```
# Stop beanstalkd
```bash
sudo service beanstalkd stop
```
# Stop UFW
```bash
sudo ufw disable
```
# Run container
```bash
docker run --rm --network="host" --env-file .env.impronta-local -d 5tapi:latest
docker run --rm -p 81:80 -p 11301:11300 --env-file .env.impronta-local -d 5tapi:latest
docker run --rm -it -p 8081:8080/tcp 5tvue:latest
```
# go to localhost
```bash
http://localhost/

```
# Stop container
```bash 
docker stop $(docker ps -a -q)
```
# clear docker
```bash
docker system prune -a
```