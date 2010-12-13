<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>PageRankGraph</title>
<link rel="stylesheet" type="text/css" href="http://static.openheatmap.com/css/mainstyle.css"/>
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js" type="text/javascript"></script>
<script type="text/javascript" src="http://static.openheatmap.com/scripts/urlparam.js"></script>
<script src="jquery.networkgraph.js" type="text/javascript"></script>
<script type='text/javascript'>

g_networkGraph = null;

$(function()
{
    $('#websiteform').bind('submit', onFormSubmit);

    g_networkGraph = new $().NetworkGraph('#graph_container');
    g_networkGraph.drawNode = drawDomainNode;
    
    if (window.location.hash.length>1)
    {
        $('#websiteaddress').val(window.location.hash.substr(1));
        startGraphLoad('http://'+window.location.hash.substr(1));
    }
});

function onFormSubmit()
{
    var websiteAddress = 'http://'+$('#websiteaddress').val();

    window.location.hash = '#'+$('#websiteaddress').val();

    startGraphLoad(websiteAddress);
    
    return false;
}

function startGraphLoad(websiteAddress)
{
    $('#loading_message').html('<img src="loading.gif"/>');
    
    g_networkGraph.removeAll();
    removeAllDomainNodes();
    
    var fetchURL = 'getgraphdata.php?domain='+encodeURIComponent(websiteAddress);
    
    $.getJSON(fetchURL, onGraphDataLoaded);
}

function onGraphDataLoaded(data)
{
    $('#loading_message').empty();

    var mainDomain = data.domain;
    var inboundLinks = data.inbound_links;
    
    var maxJuice = 0;
    var totalJuice = 0;
    
    for (var inboundDomain in inboundLinks)
    {
        var inboundLink = inboundLinks[inboundDomain];
        
        var inboundPageRank = inboundLink.page_rank;
        var inboundLinkCount = inboundLink.link_count;
        
        var inboundInfo = inboundLink.domain_info;
        
        var inboundPageCount = inboundInfo.page_count;
        
        // What we really want is the total number of outbound links from this domain, but
        // since that isn't available, use the total number of pages on the domain as a very
        // rough approximation
        
        var juice = (inboundLinkCount*(inboundPageRank/inboundPageCount));
        
        inboundLink.juice = juice;
        
        maxJuice = Math.max(maxJuice, juice);
        totalJuice += juice;
    }

    g_networkGraph.addNode(mainDomain, {
        'isUnmoveable': true,
        'startX': (g_networkGraph.width/2),
        'startY': (g_networkGraph.height/2),
        'tooltip': 'Rank '+data.page_rank+' from '+data.inbound_link_count+' links'
    });

    for (var inboundDomain in inboundLinks)
    {
        var inboundLink = inboundLinks[inboundDomain];

        var juice = inboundLink.juice;
        var normalizedJuice = (juice/maxJuice);
        var juicePercentage = Math.ceil((juice/totalJuice)*100);
        
        var strength = Math.max( 0.1, (2.0*normalizedJuice));
        var width = Math.max(1.0, (10.0*normalizedJuice));
        
        g_networkGraph.addNode(inboundDomain, {
            'tooltip': juicePercentage+'% contribution from '+inboundLink.link_count+' links'
        });

        g_networkGraph.addEdge(mainDomain, inboundDomain, {
            'strength': strength,
            'edgeWidth': width
        });
        
        var inboundInfo = inboundLink.domain_info;

        var interconnectLinks = inboundInfo.inbound_links;

        for (var interconnectDomain in interconnectLinks)
        {
            if (typeof inboundLinks[interconnectDomain] === 'undefined')
                continue;

//            g_networkGraph.addEdge(inboundDomain, interconnectDomain, {
//                'strength': 0.01,
//                'edgeColor': 'rgba(0,0,0,0)'
//            });
        
        }
    }
}

function drawDomainNode(context, node)
{
    var id = getSelectorForNode(node);
    var nodeSelector = '#'+id;
    var domainNode = $(nodeSelector);
    if (domainNode.length==0)
        domainNode = createDomainNode(node);
    
    var x = (node.x-(domainNode.width()/2));
    var y = (node.y-(domainNode.height()/2));

    domainNode.css({
        'left': x,
        'top': y
    });
}

function removeAllDomainNodes()
{
    $('.domain_node').remove();
}

function getSelectorForNode(node)
{
    var id = node.id;
    id = id.replace(/[^a-zA-Z0-9]/g, '_');

    return 'domain_node_'+id;
}

function createDomainNode(node)
{
    var label = node.id;

    label = label.replace('http://', '');
    label = label.replace('www.', '');
    label = label.replace('.com', '');

    var url = 'http://blekko.com/ws/'
        +encodeURIComponent('http://'+node.id.replace('http://', ''))
        +'+/seo';

    var id = getSelectorForNode(node);

    var tooltip = node.data.tooltip;

    var result = $(
         '<div '
        +'class="domain_node" '
        +'id="'+id+'" '
        +'style="'
        +'position: absolute; '
        +'background: rgba(224, 224, 224, 0.5); '
        +'border-color: rgba(0, 0, 0, 0.5); '
        +'border-style: solid; '
        +'border-width: 1px; '
        +'" '
        +'title="'+tooltip+'" '
        +'>'
        +'<a '
        +'href="'+url+'" '
        +'target="_blank" '
        +'>'
        +label
        +'</a>'
        +'</div>'
    );
    
    $('#graph_container').append(result);
    
    return result;
}

</script>
</head>

<body>

<div class="ui-corner-all" style="font-size: 175%; margin-top: 0px; margin-left: 150px; width: 800px;">

  <div style="float:left; width: 620px;"><h2>Where do search rankings come from?</h2>

  <br>
  Search engines use algorithms like <a href="http://en.wikipedia.org/wiki/PageRank">PageRank</a> to decide which sites show up high on the results page, and which are banished to the bottom.
  </div>

  <div style="float:right;"><img src="pageranklogo.png"/></div>

  <div style="clear: both;"></div>

  <div>
  <a href="">PageRankGraph</a> helps you understand how these rankings are calculated, by showing the sites that link to a domain, with an estimate of how much influence each site has on the target domain's score.
  </div>
  <br>

  <div>
  Enter a website address below, and you'll get a map of all the links (<i>requires Firefox, Chrome or Safari</i>)
  </div>
  <br>

  <form id="websiteform" style="height:24px;">
  http://<input type="text" size="40" name="websiteaddress" id="websiteaddress" value=""/>
  <input type="submit" value="Go"/>
  <span id="loading_message"></span>
  </form>
  <br>
  
  <div id="graph_container" style="width: 800px; height: 400px; position: relative;">
  </div>

  <div>
  Created by <a href="http://petewarden.typepad.com/">Pete Warden</a>
  </div>
  <br>

  <div>
  <a href="http://blekko.com">Blekko</a> made this possible by releasing detailed SEO information on the sites they index. If you find this tool useful, check out the great features their search interface offers.
  </div>
  <br>

  <div>
  The calculations are only very approximate, the full process of generating rankings is complex and closely-guarded and the source data is incomplete, but this should give you a feel for how much links from different sites are worth.
  </div>
  <br>

  <div>
  The lines in the graph represent inbound links that are boosting the target site's ranking. The more juice a domain is delivering, the thicker and stronger the connection is. This means that the most helpful domains are drawn close to the center of the picture, while less important ones are left on the edges. If you mouse-over each domain, you'll see a tooltip giving more information.
  </div>
  <br>

  <div>
  Full code for PageRankGraph is available at <a href="http://github.com/petewarden/pagerankgraph">github.com/petewarden/pagerankgraph</a>
  </div>
  <br>  

</div>

</body>
</html>
