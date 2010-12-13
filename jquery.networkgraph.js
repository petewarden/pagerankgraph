/**
* JQuery plugin to lay out and render a network graph consisting of nodes joined by edges
*
* author Pete Warden
*/

(function ($) {

    $.fn.NetworkGraph = function(targetSelector)
    {
        this.__construct__ = function(targetSelector) {

            this.width = $(targetSelector).width();
            this.height = $(targetSelector).height();

            this.graphLayout = new GraphLayout(this.width, this.height);

            // See http://diveintohtml5.org/detect.html#canvas
            var hasCanvas = !!document.createElement('canvas').getContext;
            
            if (!hasCanvas)
            {
                $(targetSelector).html('<br><br><center>This site requires HTML5/Canvas support, available in Firefox, Safari and Chrome</center>');
                return;
            }
        
            this.canvas = this.createCanvas(this.width, this.height);
            
            $(targetSelector)
            .empty()
            .append(this.canvas);

            var myThis = this;

            this.timer = setInterval( function() { myThis.heartbeat(); }, 100);
            
            this.settings = {
                'edgeColor': '#000000',
                'edgeWidth': 1.0,
                'textColor': '#000000',
                'textFont': '18px Baskerville, Times New Roman, Serif'
            };
        };

        this.createCanvas = function(width, height) {
            return $(
                '<canvas '
                +'width="'+width+'" '
                +'height="'+height+'"'
                +'"></canvas>'
            );
        };

        this.beginDrawing = function(canvas) {
            var context = canvas.get(0).getContext('2d');
            context.save();
            return context;
        };

        this.endDrawing = function(context) {
            context.restore();
        };

        this.fillRect = function(destination, x, y, width, height, color)
        {
            var context = this.beginDrawing(destination);
            context.fillStyle = this.colorStringFromNumber(color);
            context.fillRect(x, y, width, height);
            this.endDrawing(context);
        };

        this.colorStringFromNumber = function(colorNumber, alpha)
        {
            var red = (colorNumber>>16)&0xff;
            var green = (colorNumber>>8)&0xff;
            var blue = (colorNumber>>0)&0xff;

            if (typeof alpha === 'undefined')
                alpha = 1.0;
                
            var result = 'rgba(';
            result += red;
            result += ',';
            result += green;
            result += ',';
            result += blue;
            result += ',';
            result += alpha;
            result += ')';
            
            return result;
        };

        this.heartbeat = function()
        {
            this.graphLayout.update();
            this.draw();
        };

        this.draw = function()
        {
            var graphLayout = this.graphLayout;
        
            context = this.beginDrawing(this.canvas);
 
            context.clearRect(0, 0, this.width, this.height);
           
            var nodes = graphLayout.nodes;
            var edges = graphLayout.edges;

            for (var startId in edges)
            {
                var startNode = nodes[startId];
            
                var edgeList = edges[startId];
                for (var endId in edgeList)
                {
                    var endNode = nodes[endId];
                    var edge = edgeList[endId];

                    this.drawEdge(context, startNode, endNode, edge);
                }
            }
            
            for (var nodeId in nodes)
            {
                var node = nodes[nodeId];
                
                this.drawNode(context, node);
            }
            
            this.endDrawing(context);
        };
        
        this.drawEdge = function(context, startNode, endNode, edge)
        {
            context.beginPath();
            
            if (typeof edge.data.edgeColor !== 'undefined')
                context.strokeStyle = edge.data.edgeColor;
            else
                context.strokeStyle = this.settings.edgeColor;
                            
            if (typeof edge.data.edgeWidth !== 'undefined')
                context.lineWidth = edge.data.edgeWidth;
            else
                context.lineWidth = this.settings.edgeWidth;
            
            context.moveTo(startNode.x, startNode.y);
            context.lineTo(endNode.x, endNode.y);
            
            context.closePath();
            context.stroke();
        };

        this.drawNode = function(context, node)
        {
            var text = node.id;
            
            var metrics = context.measureText(text);
            var textWidth = (metrics.width+10);
            var textHeight = (14);
            
            var x = (node.x-(textWidth/2));
            var y = (node.y+(textHeight/2));

            if (typeof node.data.textColor !== 'undefined')
                context.fillStyle = node.data.textColor;
            else
                context.fillStyle = this.settings.textColor;

            if (typeof node.data.textFont !== 'undefined')
                context.font = node.data.textFont;
            else
                context.font = this.settings.textFont;
        
            context.fillText(text, x, y);        
        };
        
        this.addNode = function(nodeId, data) {
            this.graphLayout.addNode(nodeId, data);
        };

        this.addEdge = function(startId, endId, data) {
            this.graphLayout.addEdge(startId, endId, data);
        };

        this.removeAll = function() {
            this.graphLayout.removeAll();
        };

        this.__construct__(targetSelector);

        return this;
    };

}(jQuery));

function GraphLayout(width, height)
{
    this.nodes = {};
    this.edges = {};
    this.reversedEdges = {};
    this.settings = {
        'friction': 0.15,
        'repulsion': 40,
        'edgeAttraction': 30,
        'boundaryZone': 20,
        'stepAmount': 0.1
    };
    this.width = width;
    this.height = height;
    
    return this;
};

GraphLayout.prototype.addNode = function(id, data)
{
    if (typeof data === 'undefined')
        data = {};

    var x;
    if (typeof data.startingX !== 'undefined')
        x = data.startingX;
    else
        x = (this.width*(0.45+(Math.random()*0.1)));

    var y;
    if (typeof data.startingY !== 'undefined')
        y = data.startingY;
    else
        y = (this.height*(0.45+(Math.random()*0.1)));

    this.nodes[id] = new LayoutNode(id, x, y, data);
}

GraphLayout.prototype.addEdge = function (startId, endId, data)
{
    if (typeof data === 'undefined')
        data = {};

    if (typeof this.edges[startId] == 'undefined')
        this.edges[startId] = {};

    if (typeof this.reversedEdges[endId] == 'undefined')
        this.reversedEdges[endId] = {};

    this.edges[startId][endId] = new LayoutEdge(startId, endId, data);
    this.reversedEdges[endId][startId] = new LayoutEdge(startId, endId, data);
};

GraphLayout.prototype.update = function()
{
    this.calculateAccelerations();
    this.updateVelocities();
    this.applyFriction();
    this.updatePositions();
};

GraphLayout.prototype.calculateAccelerations = function()
{
    this.resetAccelerations();
    this.calculateNodeRepulsions();
    this.calculateEdgeAttractions();
    this.calculateBoundaryRepulsions();
};

GraphLayout.prototype.resetAccelerations = function()
{
    for (var nodeId in this.nodes)
    {
        var node = this.nodes[nodeId];
        node.ax = 0.0;
        node.ay = 0.0;
    }
};

GraphLayout.prototype.calculateNodeRepulsions = function()
{
    var recipRepulsion = 1/this.settings.repulsion;

    for (var myNodeId in this.nodes)
    {
        var myNode = this.nodes[myNodeId];

        for (var otherNodeId in this.nodes)
        {
            if (myNodeId == otherNodeId)
                continue;
                
            var otherNode = this.nodes[otherNodeId];

            var dx = (myNode.x-otherNode.x);
            var fx = (dx * recipRepulsion);

            var dy = (myNode.y-otherNode.y);
            var fy = (dy * recipRepulsion);

            var distanceSquared = ((fx*fx)+(fy*fy));
            if (distanceSquared<0.001)
            {
                distanceSquared = 1;
                dx = Math.random();
                dy = Math.random();
            }
            
            myNode.ax += (dx/distanceSquared);
            myNode.ay += (dy/distanceSquared);
        }
    }
};

GraphLayout.prototype.calculateEdgeAttractions = function()
{
    var recipEdgeAttraction = 1/this.settings.edgeAttraction;

    for (var myNodeId in this.nodes)
    {
        var myNode = this.nodes[myNodeId];
        
        for (var i=0; i<2; i+=1)
        {
            var myEdges;
            if (i==0)
                myEdges = this.edges[myNodeId];
            else
                myEdges = this.reversedEdges[myNodeId];

            for (var otherNodeId in myEdges)
            {
                var otherNode = this.nodes[otherNodeId];

                var edge = myEdges[otherNodeId];
                
                var strength = 1.0;
                if (typeof edge.data.strength !== 'undefined')
                    strength *= edge.data.strength;
                    
                var dx = (myNode.x-otherNode.x);
                var dy = (myNode.y-otherNode.y);

                var distanceSquared = ((dx*dx)+(dy*dy));
                var distance = Math.sqrt(distanceSquared);
                
                var force = (distance*recipEdgeAttraction*strength);
                
                myNode.ax -= (dx*force);
                myNode.ay -= (dy*force);
            }
        }
    }
};

GraphLayout.prototype.calculateBoundaryRepulsions = function()
{
    var boundaryZone = this.settings.boundaryRepulsion;
    var recipRepulsion = 1/this.settings.repulsion;

    var width = this.width;
    var height = this.height;

    for (var myNodeId in this.nodes)
    {
        var myNode = this.nodes[myNodeId];
        
        if (myNode.x<0)
        {
            var dx = (0-myNode.x);
            myNode.ax += dx;
        }

        if (myNode.y<0)
        {
            var dy = (0-myNode.y);
            myNode.ay += dy;
        }

        if (myNode.x>width)
        {
            var dx = (width-myNode.x);
            myNode.ax += dx;
        }

        if (myNode.y>height)
        {
            var dy = (height-myNode.y)
            myNode.ay += dy;
        }

    }
};

GraphLayout.prototype.applyFriction = function()
{
    var oneMinusFriction = (1.0-this.settings.friction);
    for (var nodeId in this.nodes)
    {
        var node = this.nodes[nodeId];
        node.vx *= oneMinusFriction;
        node.vy *= oneMinusFriction;
    }
}

GraphLayout.prototype.updateVelocities = function()
{
    var stepAmount = this.settings.stepAmount;
    for (var nodeId in this.nodes)
    {
        var node = this.nodes[nodeId];
        node.vx += (node.ax*stepAmount);
        node.vy += (node.ay*stepAmount);
    }
};

GraphLayout.prototype.updatePositions = function()
{
    for (var nodeId in this.nodes)
    {
        var node = this.nodes[nodeId];
        if ((typeof node.data.isUnmoveable !== 'undefined') && node.data.isUnmoveable)
            continue;
            
        node.x += node.vx;
        node.y += node.vy;
    }
};

GraphLayout.prototype.removeAll = function()
{
    this.nodes = {};
    this.edges = {};
    this.reversedEdges = {};
}

function LayoutNode(id, x, y, data)
{
    this.id = id;
    this.x = x;
    this.y = y;
    this.data = data;
    this.vx = 0;
    this.vy = 0;
}

function LayoutEdge(startId, endId, data)
{
    this.startId = startId;
    this.endId = endId;
    this.data = data;
}
