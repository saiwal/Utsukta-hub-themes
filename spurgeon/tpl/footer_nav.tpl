                <div class="column lg-5 md-6 tab-12">
                    <div class="row">
                        <div class="column lg-6">
                            <h4>Categories</h4>
                            <ul class="link-list">
                                <li><a href="category.html">Lifestyle</a></li>
                                <li><a href="category.html">Workplace</a></li>
                                <li><a href="category.html">Inspiration</a></li>
                                <li><a href="category.html">Design</a></li>
                                <li><a href="category.html">Health</a></li>
                                <li><a href="category.html">Photography</a></li>
                            </ul>
                        </div>
                        <div class="column lg-6">
                            <h4>Site Links</h4>
                            <ul class="link-list">
                              {{if $is_owner}}
                              <!-- Starred user apps -->
                                  {{foreach $nav_apps as $nav_app}}
                                  {{$nav_app}}
                                  {{/foreach}}
                              <li class="nav-header"><a class="nav-link" href="/apps"><i class="bi bi-plus-lg"></i>
                                  <p>{{$addapps}}</p>
                                </a></li>
                              {{else}}
                              <li class="nav-header" aria-disabled="true">{{$sysapps}}</li>
                              <!-- System apps -->
                              {{foreach $nav_apps as $nav_app}}
                              {{$nav_app}}
                              {{/foreach}}
                              {{/if}}

                                <li><a href="index.html">Home</a></li>
                                <li><a href="category.html">Categories</a></li>
                                <li><a href="category.html">Blog</a></li>
                                <li><a href="about.html">About</a></li>
                                <li><a href="about.html">Contact</a></li>
                                <li><a href="#0">Terms & Policy</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

