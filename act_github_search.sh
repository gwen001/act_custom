#!/bin/bash


function usage {
    echo "Usage: "$0" <organization> <token>"
    if [ -n "$1" ] ; then
		echo "Error: "$1"!"
    fi
    exit
}

if [ ! $# -eq 2 ] ; then
    usage
fi

o_org=$1
o_token=$2

t_dorks=(
	'-f .npmrc -s _auth'
	'-f .dockercfg -s auth'
	'-s private -e pem'
	'-s private -e ppk'
	'-f id_rsa'
	'-f id_dsa'
	'-s "mysql dump" -e sql'
	'-s "mysql dump password"-e sql'
	'-f credentials -s aws_access_key_id'
	'-f .s3cfg'
	'-f wp-config.php'
	'-f .htpasswd'
	'-f .env -s DB_USERNAME'
	'-f .git-credentials'
	'-s PT_TOKEN -l bash'
	'-f .bashrc -s password'
	'-f .bashrc -s mailchimp'
	'-f .bash_profile -s aws'
	'-s password'
	'-s amazonaws.com'
	'-s storage.google'
	'-s storage.cloud.google'
	'-s digitaloceanspaces.com'
	'-s api.forecast.io -e json'
	'-s mongolab.com -e json'
	'-s mongolab.com -e yaml'
	'-s conn.login -e js'
	'-s SF_USERNAME'
	'-l shell -s HEROKU_API_KEY'
	'-l json -s HEROKU_API_KEY'
	'-f .netrc -s password'
	'-f _netrc -s password'
	'-f hub -s oauth_token'
	'-f robomongo.json'
	'-f filezilla.xml -s Pass'
	'-f recentservers.xml -s Pass'
	'-f config.json -s auths'
	'-f idea14.key'
	'-f config -s irc_pass'
	'-f connections.xml'
	'-f express.conf'
	'-f .pgpass'
	'-f proftpdpasswd'
	'-f ventrilo_srv.ini'
	'-s "[WFClient] Password=" -e ica'
	'-f server.cfg -s "rcon password"'
	'-s JEKYLL_GITHUB_TOKEN'
	'-f .bash_history'
	'-f .cshrc'
	'-f .history'
	'-f .sh_history'
	'-f sshd_config'
	'-f dhcpd.conf'
	'-f configuration.php -s JConfig'
	'-f config.php -s dbpasswd'
	'-f config.php -s pass'
	'-s shodan_api_key'
	'-f shadow'
	'-f passwd'
	'-f .htpasswd'
	'-f .htaccess'
	'-s API_key'
	'-s secret_key'
	'-s aws_key'
	'-s github_token'
	'-s app_secret'
	'-s app_key'
	'-s apikey'
	'-s fb_secret'
	'-s google_secret'
	'-s gsecre'
)

i=0
o_result=100
n_dork=${#t_dorks[@]}
n_sleep=2
n_found=0

for i in $(seq 0 $n_dork) ; do
	option="-n -t $o_token -r $o_result -o $o_org $(echo ${t_dorks[$i]})"
	c="github-search $option"
	echo $c
	s=$(github-search $option)
	found=`echo $s | egrep -i "result\(s\) found"`
	if [ -n "$found" ] ; then
		n_found=$(expr $n_found + 1)
		echo "$s"
		echo
	fi
	sleep $n_sleep
done

echo ">>> $n_found expression found. <<<"
