<p> Hey {{$name}}, Welcome to The Shop Company. </p> <br>
<p> You are successfully registered!</p>
<p> Your profile information:</p>
<ul>
    <li>E-mail: {{$email}}</li>

    @if(!empty($github))
        <li>Github: {{$github}}</li>
    @endif

    @if(!empty($trello))
        <li>Trello: {{$trello}}</li>
    @endif

    @if(!empty($slack))
        <li>Slack : {{$slack}}</li>
    @endif

</ul>
@if (!empty($teamSlack))
    <p> We would like to invite you to our slack team.</p>

    <p>Please, go visit <a href="https://slack.com/signin">https://slack.com/signin</a> and join us!</p>

    <ul>
        <li> Slack name: {{ $teamSlack['teamName'] }}</li>
        <li> Slack domain: {{ $teamSlack['teamDomain'] }}</li>
        <li> Slack email domain: {{ $teamSlack['emailDomain'] }}</li>
    </ul>
@endif
