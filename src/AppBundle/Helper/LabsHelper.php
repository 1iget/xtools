<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 1/16/17
 * Time: 16:38
 */

namespace AppBundle\Helper;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LabsHelper
{
    public $dbName;
    public $client;
    private $container;
    private $url;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function checkEnabled($tool) {
        if (!$this->container->getParameter("enable.$tool")) {
            throw new NotFoundHttpException('This tool is disabled');
        }
    }

    // Todo: Handle failure better
    public function databasePrepare($project = 'wiki', $route = 'homepage') {
        if ($this->container->getParameter('app.single_wiki')) {
            $dbName = $this->container->getParameter('database_replica_name');
            $wikiName = 'wiki';
            $url = $this->container->getParameter('wiki_url');
        }
        else {
            // Grab the connection to the meta database
            $this->client = $this->container->get('doctrine')->getManager('meta')->getConnection();

            // Create the query we're going to run against the meta database
            $wikiQuery = $this->client->createQueryBuilder();
            $wikiQuery
                ->select(['dbName', 'name', 'url'])
                ->from('wiki')
                ->where($wikiQuery->expr()->eq('dbname', ':project'))
                // The meta database will have the project's URL stored as https://en.wikipedia.org
                //  so we need to query for it accordingly, trying different variations the user might have inputted
                ->orwhere($wikiQuery->expr()->like('url', ':projectUrl'))
                ->orwhere($wikiQuery->expr()->like('url', ':projectUrl2'))
                ->setParameter('project', $project)
                ->setParameter('projectUrl', "https://$project")
                ->setParameter('projectUrl2', "https://$project.org");
            $wikiStatement = $wikiQuery->execute();

            // Fetch the wiki data
            $wikis = $wikiStatement->fetchAll();

            // Throw an exception if we can't find the wiki
            if (sizeof($wikis) < 1) {
                // TODO: Fix so that we're rendering a flash rather than dying...
                throw new Exception('Unable to find project');
                //$this->container->get('controller')->addFlash('notice', ["nowiki", $project]);
                //return $this->container->redirectToRoute($route);
            }

            // Grab the data we need out of it, using the first result (in the rare event there are more than one)
            $dbName = $wikis[0]['dbName'];
            $wikiName = $wikis[0]['name'];
            $url = $wikis[0]['url'];
        }

        if ($this->container->getParameter('app.is_labs') && substr($dbName, -2) != '_p') {
            $dbName .= '_p';
        }

        $this->dbName = $dbName;
        $this->url = $url;

        return ['dbName' => $dbName, 'wikiName' => $wikiName, 'url' => $url];
    }

    /**
     * All mapping tables to environment-specific names, as specified in config/table_map.yml
     * Used for example to convert revision -> revision_replica
     * @param  string   $table  Table name
     * @param  [string] $dbName Database name
     * @return $string  Converted table name
     */
    public function getTable( $table, $dbName = null ) {
        $retVal = $table;
        if( $this->container->hasParameter( "app.table.$table" ) ) {
            $retVal = $this->container->getParameter( "app.table.$table" );
        }

        // use class variable if not set via function parameter
        $dbName = $dbName || $this->dbName;

        if ( isset($dbName) ) {
            $retVal = "$this->dbName.$retVal";
        }

        return $retVal;
    }
}