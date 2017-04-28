<p> Hey {{$name}}</p> <br>
<p> You have successfully joined application "{{$appName}}"!</p>
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
