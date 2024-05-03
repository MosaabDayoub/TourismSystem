<?php

namespace App\Services\GenerateTrip;

use Fhaculty\Graph\Graph ;
use Fhaculty\Graph\Vertex;
use Fhaculty\Graph\Edge\Base;
use Fhaculty\Graph\Edge\Directed ;
use Graphp\GraphViz\GraphViz;
use App\Services\GenerateTripController\CustomGraph;
use SplPriorityQueue;

class DijkstraAlgorithm
{
    public static function allShortestPaths(Graph $graph, $sourceNode)
    {
        $distances = [];
        $previous = [];
        $nodeQueue = new SplPriorityQueue();

        foreach ($graph->getVertices() as $vertex) {
            if ($vertex !== $sourceNode) {
                // Initialize distances to all vertices as infinite
                $distances[$vertex->getId()] = PHP_INT_MAX;
                $nodeQueue->insert($vertex, PHP_INT_MAX);
            }
            $previous[$vertex->getId()] = null;
        }

        // Distance from source to source is 0
        $distances[$sourceNode->getId()] = 0;
        $nodeQueue->insert($sourceNode, PHP_INT_MAX);

        while (!$nodeQueue->isEmpty()) {
            // Find the vertex with the minimum distance from the source among the unvisited vertices
            $minVertex = $nodeQueue->extract();
            $minVertexId = $minVertex->getId();

            // update the distances for all neighbor of this node
            foreach ($minVertex->getEdges() as $edge) {
                $neighbor = $edge->getVertexEnd();

                $alt = $distances[$minVertexId] + $edge->getWeight();
                if ($alt < $distances[$neighbor->getId()]) {
                    $distances[$neighbor->getId()] = $alt;
                    $previous[$neighbor->getId()] = $minVertex;
                    // Reorder the neighbor node in the priority list with a new distance
                    $nodeQueue->insert($neighbor, $alt);
                }
            }
        }


        // return the shortst paths
        $paths = array();
        foreach ($previous as $vertexId => $vertex) {
            $path = $vertex !== null ? self::buildPath($previous, $vertex) : array();
            $paths[$vertexId] = array_reverse($path);
        }

        return array($distances, $previous, $paths);
    }

    private static function buildPath(&$previous, $vertex)
    {
        $path = array($vertex->getId());
        while ($previous[$vertex->getId()] !== null) {
            $vertex = $previous[$vertex->getId()];
            $path[] = $vertex->getId();
        }

        return $path;
    }
}
