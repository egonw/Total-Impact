#!/usr/bin/env python

import urllib2
import re
import BeautifulSoup
from BeautifulSoup import BeautifulStoneSoup 
from optparse import OptionParser

DOI_LOOKUP_URL = "http://dx.doi.org/%s"

SOURCE_URL = "http://api.facebook.com/restserver.php?method=links.getStats&urls=%s"
DEBUG = False

FACEBOOK_SHARE_PATTERN = re.compile("<share_count>(?P<stats>\d+)</share_count>", re.DOTALL)
FACEBOOK_LIKE_PATTERN = re.compile("<like_count>(?P<stats>\d+)</like_count>", re.DOTALL)
FACEBOOK_COMMENT_PATTERN = re.compile("<comment_count>(?P<stats>\d+)</comment_count>", re.DOTALL)
FACEBOOK_CLICK_PATTERN = re.compile("<click_count>(?P<stats>\d+)</click_count>", re.DOTALL)

DOI_PATTERN = re.compile("(10.(\d)+/(\S)+)", re.DOTALL)

def run_plugin(doi):
    # Right now this is only designed to look up dois
    if not DOI_PATTERN.search(doi):
        return(None)
    redirect_url = get_redirect_url(id)
    if redirect_url:
        page = get_page(redirect_url)
        response = get_stats(page)
    else:
        response = None
    return(response)
    
def get_redirect_url(doi):
    if not doi:
        return(None)
    doi_url = DOI_LOOKUP_URL % doi
    try:
        doi_redirect_url = urllib2.urlopen(doi_url).url
    except:
        doi_redirect_url = None
    #if (DEBUG):
    #    print doi_url
    #    print doi_redirect_url
    
    return(doi_redirect_url)    

def get_page(lookup):
    if not lookup:
        return(None)
    url = SOURCE_URL % lookup
    #if (DEBUG):
    #    print url
    page = urllib2.urlopen(url).read()
    #if (DEBUG):
    #    print page
    return(page) 
    
def get_stats(page):
    if not page:
        return(None)
    #print page
    soup = BeautifulStoneSoup(page)
    
    try:
        like_count = int(soup.like_count.text)
    except:
        like_count = None
        
    try:
        share_count = int(soup.share_count.text)
    except:
        share_count = None
        
    try:
        click_count = int(soup.click_count.text)
    except:
        click_count = None
        
    try:
        comment_count = int(soup.comment_count.text)
    except:
        comment_count = None
        
    stats = {"likes":like_count, "shares":share_count, "clicks":click_count, "comments":comment_count}
    return(stats)  



def main():
    parser = OptionParser(usage="usage: %prog [options] filename",
                          version="%prog 1.0")
    (options, args) = parser.parse_args()

    if len(args) != 1:
        parser.error("wrong number of arguments")
    
    doi = args[0]
    response = run_plugin(doi)
   
    print response
    return(response)


if __name__ == '__main__':
    main()  
          
#test_input = "10.1371/journal.pcbi.1000361"
#test_input = "10.1371/journal.pmed.0040215"
#test_input = "10.1371/journal.pone.0000308"

#redirect_url = get_redirect_url(test_input)
#page = get_page(redirect_url)
#stats = get_stats(page)
#print stats
