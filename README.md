This a converter for Bitbucket webhooks for Discord (PHP). 

### Running ###
1. Create webhook on Discord and remember _**id**_ and _**token**_. 
```
For example: 
https://discordapp.com/api/webhooks/123454321/abcdedcba
123454321 is ID
abcdedcba is TOKEN
```
2. Deploy this package to your webserver
3. On Bitbucket create webhook to http(s)://myserver.com/tunnel.php?id=ID&token=TOKEN
4. Try pushing something to the Bitbucket repository and see if it gets printed on Discord

![](http://s01.geekpic.net/di-FPS91H.png)
