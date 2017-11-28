build:
	composer install

deploy:
	rsync -vrc * website.education.wisc.edu:/var/www/site/grandchallenges/dashboard
