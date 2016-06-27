<?php

/**
 * Class GitHub
 */
class GitHub
{
    const HTTP_METHOD_GET = 'GET';
    const HTTP_METHOD_PUT = 'PUT';
    const HTTP_METHOD_DELETE = 'DELETE';
    const HTTP_METHOD_POST = 'POST';

    const HOST = 'https://api.github.com';

    private $token = null;
    private $repo = null;

    public function __construct($token, $repo)
    {
        $this->token = $token;
        $this->repo = $repo;
    }

    /**
     * Get issues for a repository
     * @see https://developer.github.com/v3/issues/#list-issues-for-a-repository
     *
     * @param $filters array
     * @return mixed
     */
    public function getIssues($filters)
    {
        $endpoint = sprintf('/repos/%s/issues?%s', $this->repo, $filters);
        return $this->request(self::HTTP_METHOD_GET, $endpoint);
    }

    /**
     * Get a single pull request
     * @see https://developer.github.com/v3/pulls/#get-a-single-pull-request
     *
     * For merging should use only:
     *  - mergeable_state : clean (required stuff passed, no merge conflicts)
     *  - mergeable : 1
     *
     *  - clean - required checks passed, no merge conflicts
     *  - unstable - checks still running / required checks have not passed, no merge conflicts
     *  - dirty - merge conflicts
     *
     * @param int $id pull request id
     * @return mixed
     */
    public function getPullRequest($id)
    {
        $endpoint = sprintf('/repos/%s/pulls/%d', $this->repo, $id);
        return $this->request(self::HTTP_METHOD_GET, $endpoint);
    }

    /**
     * Merge a pull request
     * @see https://developer.github.com/v3/pulls/#merge-a-pull-request-merge-button
     *
     * @param int $id
     * @return mixed
     */
    public function mergePullRequest($id)
    {
        $endpoint = sprintf('/repos/%s/pulls/%d/merge', $this->repo, $id);
        return $this->request(self::HTTP_METHOD_PUT, $endpoint, "{}");
    }

    public function deleteBranch($branch)
    {
        $endpoint = sprintf('/repos/%s/git/refs/heads/%s', $this->repo, $branch);
        return $this->request(self::HTTP_METHOD_DELETE, $endpoint);
    }

    public function getDeployments()
    {
        $endpoint = sprintf('/repos/%s/deployments?environment=stage', $this->repo);
        return $this->request(self::HTTP_METHOD_GET, $endpoint);
    }

    public function getLatestDeployment()
    {
        $deployments = $this->getDeployments();
        return current($deployments);
    }

    /**
     * @param $branch
     * @param $description
     * @return mixed
     */
    public function createDeploy($branch, $description)
    {
        $data = json_encode(array(
            'ref' => $branch,
            'auto_merge' => false,
            'environment' => 'stage',
            'required_contexts' => array(),
            "description" => $description
        ));

        $endpoint = sprintf('/repos/%s/deployments', $this->repo);
        return $this->request(self::HTTP_METHOD_POST, $endpoint, $data);
    }

    /**
     * @param $deployId
     * @param $state
     * @param $description
     * @return mixed
     *
     * Possible states:
     *  - pending
     *  - success
     */
    public function deployStatus($deployId, $state, $description)
    {
        $data = json_encode(array('state' => $state, "description" => $description));
        $endpoint = sprintf('/repos/%s/deployments/%d/statuses', $this->repo, $deployId);
        return $this->request(self::HTTP_METHOD_POST, $endpoint, $data);
    }

    /**
     * @param $method
     * @param $endpoint
     * @param null $data
     * @return mixed
     */
    private function request($method, $endpoint, $data = null)
    {
        $headers = array(
            sprintf('Authorization: token %s', $this->token)
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::HOST . $endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Peachy CI');

        if (!is_null($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $result = curl_exec($ch);

        if (!$result && curl_errno($ch) != 0) {
            echo sprintf('URL: %s', self::HOST . $endpoint) . "\n";
            die('Error: "' . curl_error($ch) . '" - Code: ' . curl_errno($ch) . "\n");
        }

        curl_close($ch);

        return json_decode($result);
    }
}
